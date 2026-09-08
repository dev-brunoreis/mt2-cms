<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Drop\GroupTextParser;
use Mt2Cms\Game\Drop\LocaleText;
use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\Proto\ProtoSchemas;

class MobDropService
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    public function clearCatalog(): void
    {
        $this->catalog = null;
    }

    public function __construct(
        private GameProfile $profile,
        private GameProtoService $protos,
        private GroupTextParser $parser,
    ) {
    }

    /**
     * @param array<string, mixed> $mob
     * @return array{
     *     drop_groups: list<array<string, mixed>>,
     *     common_drops: list<array<string, mixed>>,
     *     etc_drop: array<string, mixed>|null,
     *     spawn_groups: list<array<string, mixed>>
     * }
     */
    public function forMob(array $mob): array
    {
        $vnum = (int) ($mob['vnum'] ?? 0);
        $rank = strtoupper(trim((string) ($mob['rank'] ?? '')));
        $level = (int) ($mob['level'] ?? 0);
        $dropItem = (int) ($mob['drop_item'] ?? 0);
        $catalog = $this->catalog();

        return [
            'drop_groups' => $catalog['drop_groups'][$vnum] ?? [],
            'common_drops' => $this->commonFor($catalog['common_drops'], $rank, $level),
            'etc_drop' => $this->etcFor($catalog['etc_drops'], $dropItem),
            'spawn_groups' => $catalog['spawn_groups'][$vnum] ?? [],
        ];
    }

    /**
     * @return array{
     *     drop_groups: array<int, list<array<string, mixed>>>,
     *     common_drops: array<string, list<array<string, mixed>>>,
     *     etc_drops: array<int, array<string, mixed>>,
     *     spawn_groups: array<int, list<array<string, mixed>>>
     * }
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        [$itemNames, $originalToVnum] = $this->itemLookups();
        $mobNames = $this->nameMap(ProtoSchemas::KIND_MOB);

        $this->catalog = [
            'drop_groups' => $this->indexDropGroups($itemNames, $originalToVnum),
            'common_drops' => $this->loadCommonDrops($itemNames, $originalToVnum),
            'etc_drops' => $this->loadEtcDrops($itemNames, $originalToVnum),
            'spawn_groups' => $this->indexSpawnGroups($mobNames),
        ];

        return $this->catalog;
    }

    /**
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     * @return array<int, list<array<string, mixed>>>
     */
    private function indexDropGroups(array $itemNames, array $originalToVnum): array
    {
        $byMob = [];

        foreach ($this->parseDropGroups('mob_drop_item') as $group) {
            $this->appendMobDropGroup($byMob, $group, $itemNames, $originalToVnum, 'mob_drop_item');
        }

        foreach ($this->parseDropGroups('drop_item_group') as $group) {
            $this->appendDropItemGroup($byMob, $group, $itemNames, $originalToVnum);
        }

        return $byMob;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $byMob
     * @param array{name: string, attrs: array<string, list<string>>, items: list<list<string>>} $group
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     */
    private function appendMobDropGroup(
        array &$byMob,
        array $group,
        array $itemNames,
        array $originalToVnum,
        string $source,
    ): void {
        $mobVnum = (int) ($group['attrs']['mob'][0] ?? 0);

        if ($mobVnum < 1) {
            return;
        }

        $type = strtolower((string) ($group['attrs']['type'][0] ?? 'drop'));
        $items = [];

        foreach ($group['items'] as $row) {
            $resolved = $this->resolveItem($row[0] ?? '', $itemNames, $originalToVnum);

            if ($resolved === null) {
                continue;
            }

            $items[] = [
                'vnum' => $resolved['vnum'],
                'name' => $resolved['name'],
                'count' => (int) ($row[1] ?? 1),
                'chance' => $row[2] ?? '',
                'rare' => $row[3] ?? null,
            ];
        }

        $byMob[$mobVnum][] = [
            'name' => $group['name'],
            'type' => $type,
            'kill_drop' => isset($group['attrs']['kill_drop'][0]) ? (int) $group['attrs']['kill_drop'][0] : null,
            'level_limit' => isset($group['attrs']['level_limit'][0]) ? (int) $group['attrs']['level_limit'][0] : null,
            'source' => $source,
            'items' => $items,
        ];
    }

    /**
     * @param array<int, list<array<string, mixed>>> $byMob
     * @param array{name: string, attrs: array<string, list<string>>, items: list<list<string>>} $group
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     */
    private function appendDropItemGroup(
        array &$byMob,
        array $group,
        array $itemNames,
        array $originalToVnum,
    ): void {
        $mobVnum = (int) ($group['attrs']['mob'][0] ?? 0);

        if ($mobVnum < 1) {
            return;
        }

        $items = [];

        foreach ($group['items'] as $row) {
            $resolved = $this->resolveItem($row[0] ?? '', $itemNames, $originalToVnum);

            if ($resolved === null) {
                continue;
            }

            $items[] = [
                'vnum' => $resolved['vnum'],
                'name' => $resolved['name'],
                'count' => (int) ($row[2] ?? 1),
                'chance' => $row[1] ?? '',
                'rare' => null,
            ];
        }

        $byMob[$mobVnum][] = [
            'name' => $group['name'],
            'type' => 'drop',
            'kill_drop' => null,
            'level_limit' => null,
            'source' => 'drop_item_group',
            'items' => $items,
        ];
    }

    /**
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadCommonDrops(array $itemNames, array $originalToVnum): array
    {
        $byRank = [];

        foreach ($this->profile->commonRanks() as $rank) {
            $byRank[$rank] = [];
        }

        $path = $this->profile->dropPath('common_drop_item');

        if (!is_file($path) || !is_readable($path)) {
            return $byRank;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return $byRank;
        }

        $lines = explode("\n", str_replace("\r\n", "\n", LocaleText::decode($raw)));
        array_shift($lines);

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = explode("\t", $line);

            foreach ($this->profile->commonRanks() as $index => $rank) {
                $offset = $index * 6;
                $levelStart = (int) ($cells[$offset + 1] ?? 0);
                $levelEnd = (int) ($cells[$offset + 2] ?? 0);
                $chance = trim((string) ($cells[$offset + 3] ?? ''));
                $itemRef = trim((string) ($cells[$offset + 4] ?? ''));

                if ($levelStart < 1 || $itemRef === '') {
                    continue;
                }

                $resolved = $this->resolveItem($itemRef, $itemNames, $originalToVnum);

                if ($resolved === null) {
                    continue;
                }

                $byRank[$rank][] = [
                    'vnum' => $resolved['vnum'],
                    'name' => $resolved['name'],
                    'count' => 1,
                    'chance' => $chance,
                    'one_in' => (int) ($cells[$offset + 5] ?? 0),
                    'level_start' => $levelStart,
                    'level_end' => $levelEnd,
                    'rank' => $rank,
                ];
            }
        }

        return $byRank;
    }

    /**
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     * @return array<int, array<string, mixed>>
     */
    private function loadEtcDrops(array $itemNames, array $originalToVnum): array
    {
        $path = $this->profile->dropPath('etc_drop_item');

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return [];
        }

        $drops = [];

        foreach (explode("\n", str_replace("\r\n", "\n", LocaleText::decode($raw))) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                continue;
            }

            $tab = strrpos($line, "\t");

            if ($tab === false) {
                continue;
            }

            $name = trim(substr($line, 0, $tab));
            $chance = trim(substr($line, $tab + 1));

            if ($name === '' || $chance === '') {
                continue;
            }

            $resolved = $this->resolveItem($name, $itemNames, $originalToVnum);

            if ($resolved === null) {
                continue;
            }

            $drops[$resolved['vnum']] = [
                'vnum' => $resolved['vnum'],
                'name' => $resolved['name'] !== '' ? $resolved['name'] : $name,
                'chance' => $chance,
            ];
        }

        return $drops;
    }

    /**
     * @param array<int, string> $mobNames
     * @return array<int, list<array<string, mixed>>>
     */
    private function indexSpawnGroups(array $mobNames): array
    {
        $groups = [];

        foreach ($this->parseDropGroups('group') as $group) {
            $groupVnum = (int) ($group['attrs']['vnum'][0] ?? 0);

            if ($groupVnum < 1) {
                continue;
            }

            $leaderVnum = (int) ($group['attrs']['leader'][1] ?? 0);
            $members = [];

            if ($leaderVnum > 0) {
                $members[] = $this->mobRef($leaderVnum, $mobNames);
            }

            foreach ($group['items'] as $row) {
                $memberVnum = (int) ($row[1] ?? 0);

                if ($memberVnum > 0) {
                    $members[] = $this->mobRef($memberVnum, $mobNames);
                }
            }

            $groups[$groupVnum] = [
                'vnum' => $groupVnum,
                'name' => $group['name'],
                'leader_vnum' => $leaderVnum,
                'leader_name' => $mobNames[$leaderVnum] ?? '',
                'members' => $members,
                'group_groups' => [],
            ];
        }

        foreach ($this->parseDropGroups('group_group') as $pack) {
            $packVnum = (int) ($pack['attrs']['vnum'][0] ?? 0);

            if ($packVnum < 1) {
                continue;
            }

            foreach ($pack['items'] as $row) {
                $memberGroup = (int) ($row[0] ?? 0);

                if (!isset($groups[$memberGroup])) {
                    continue;
                }

                $groups[$memberGroup]['group_groups'][] = [
                    'vnum' => $packVnum,
                    'name' => $pack['name'],
                    'prob' => (int) ($row[1] ?? 1),
                ];
            }
        }

        $byMob = [];

        foreach ($groups as $group) {
            $seen = [];

            foreach ($group['members'] as $member) {
                $mobVnum = (int) $member['vnum'];

                if ($mobVnum < 1 || isset($seen[$mobVnum])) {
                    continue;
                }

                $seen[$mobVnum] = true;
                $entry = $group;
                $entry['is_leader'] = $mobVnum === (int) $group['leader_vnum'];
                $byMob[$mobVnum][] = $entry;
            }
        }

        return $byMob;
    }

    /**
     * @return list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}>
     */
    private function parseDropGroups(string $key): array
    {
        try {
            $path = $this->profile->dropPath($key);
        } catch (\RuntimeException) {
            return [];
        }

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        return $this->parser->parse(LocaleText::decode($raw));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $common
     * @return list<array<string, mixed>>
     */
    private function commonFor(array $common, string $rank, int $level): array
    {
        $rows = $common[$rank] ?? [];

        if ($rows === [] || $level < 1) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $level >= (int) $row['level_start'] && $level <= (int) $row['level_end'],
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $etc
     * @return array<string, mixed>|null
     */
    private function etcFor(array $etc, int $dropItem): ?array
    {
        if ($dropItem < 1) {
            return null;
        }

        return $etc[$dropItem] ?? null;
    }

    /**
     * @param array<int, string> $itemNames
     * @param array<string, int> $originalToVnum
     * @return array{vnum: int, name: string}|null
     */
    private function resolveItem(string $ref, array $itemNames, array $originalToVnum): ?array
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            $vnum = (int) $ref;

            return [
                'vnum' => $vnum,
                'name' => $itemNames[$vnum] ?? '',
            ];
        }

        $vnum = $originalToVnum[$ref] ?? 0;

        if ($vnum < 1) {
            return [
                'vnum' => 0,
                'name' => $ref,
            ];
        }

        return [
            'vnum' => $vnum,
            'name' => $itemNames[$vnum] !== '' ? $itemNames[$vnum] : $ref,
        ];
    }

    /**
     * @param array<int, string> $mobNames
     * @return array{vnum: int, name: string}
     */
    private function mobRef(int $vnum, array $mobNames): array
    {
        return [
            'vnum' => $vnum,
            'name' => $mobNames[$vnum] ?? '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function nameMap(string $kind): array
    {
        $map = [];

        foreach ($this->protos->all($kind) as $row) {
            $vnum = (int) ($row['vnum'] ?? 0);

            if ($vnum > 0) {
                $map[$vnum] = (string) ($row['locale_name'] ?? '');
            }
        }

        return $map;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private function itemLookups(): array
    {
        $names = [];
        $original = [];

        foreach ($this->protos->all(ProtoSchemas::KIND_ITEM) as $row) {
            $vnum = (int) ($row['vnum'] ?? 0);

            if ($vnum < 1) {
                continue;
            }

            $names[$vnum] = (string) ($row['locale_name'] ?? '');
            $protoName = LocaleText::decode((string) ($row['name'] ?? ''));

            if ($protoName !== '') {
                $original[$protoName] = $vnum;
            }
        }

        return [$names, $original];
    }
}
