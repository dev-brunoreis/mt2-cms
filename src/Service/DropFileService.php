<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Drop\GroupTextParser;
use Mt2Cms\Game\Drop\GroupTextWriter;
use Mt2Cms\Game\Drop\LocaleText;
use Mt2Cms\Game\GameProfile;

class DropFileService
{
    public function __construct(
        private GameProfile $profile,
        private GroupTextParser $parser,
        private GroupTextWriter $writer,
    ) {
    }

    /**
     * @return list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}>
     */
    public function mobDropGroupsFor(int $mobVnum): array
    {
        $groups = [];

        foreach ($this->loadGroupFile('mob_drop_item') as $group) {
            if ((int) ($group['attrs']['mob'][0] ?? 0) === $mobVnum) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}> $mobGroups
     */
    public function saveMobDropGroupsFor(int $mobVnum, array $mobGroups): void
    {
        $all = [];
        $replaced = false;

        foreach ($this->loadGroupFile('mob_drop_item') as $group) {
            if ((int) ($group['attrs']['mob'][0] ?? 0) === $mobVnum) {
                $replaced = true;

                continue;
            }

            $all[] = $group;
        }

        foreach ($mobGroups as $group) {
            $group['attrs']['mob'] = [(string) $mobVnum];
            $all[] = $group;
        }

        if (!$replaced && $mobGroups === []) {
            return;
        }

        $this->writeGroupFile('mob_drop_item', $all);
    }

    /**
     * @return list<array{name: string, chance: string, vnum: int|null}>
     */
    public function etcDrops(): array
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

            $drops[] = [
                'name' => trim(substr($line, 0, $tab)),
                'chance' => trim(substr($line, $tab + 1)),
                'vnum' => null,
            ];
        }

        return $drops;
    }

    /**
     * @param list<array{name: string, chance: string}> $drops
     */
    public function saveEtcDrops(array $drops): void
    {
        $lines = [];

        foreach ($drops as $drop) {
            $name = trim((string) ($drop['name'] ?? ''));
            $chance = trim((string) ($drop['chance'] ?? ''));

            if ($name === '' || $chance === '') {
                continue;
            }

            $lines[] = $name . "\t" . $chance;
        }

        $this->writeRawFile('etc_drop_item', implode("\n", $lines) . ($lines !== [] ? "\n" : ''));
    }

    /**
     * @return array{header: string, ranks: array<string, list<array<string, mixed>>>}
     */
    public function commonDrops(): array
    {
        $path = $this->profile->dropPath('common_drop_item');
        $ranks = [];

        foreach ($this->profile->commonRanks() as $rank) {
            $ranks[$rank] = [];
        }

        if (!is_file($path) || !is_readable($path)) {
            return ['header' => '', 'ranks' => $ranks];
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return ['header' => '', 'ranks' => $ranks];
        }

        $lines = explode("\n", str_replace("\r\n", "\n", LocaleText::decode($raw)));
        $header = array_shift($lines) ?? '';

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = explode("\t", $line);

            foreach ($this->profile->commonRanks() as $index => $rank) {
                $offset = $index * 6;
                $levelStart = (int) ($cells[$offset + 1] ?? 0);
                $itemRef = trim((string) ($cells[$offset + 4] ?? ''));

                if ($levelStart < 1 || $itemRef === '') {
                    continue;
                }

                $ranks[$rank][] = [
                    'label' => trim((string) ($cells[$offset] ?? '')),
                    'level_start' => $levelStart,
                    'level_end' => (int) ($cells[$offset + 2] ?? 0),
                    'chance' => trim((string) ($cells[$offset + 3] ?? '')),
                    'item_ref' => $itemRef,
                    'one_in' => (int) ($cells[$offset + 5] ?? 0),
                ];
            }
        }

        return ['header' => $header, 'ranks' => $ranks];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $ranks
     */
    public function saveCommonDrops(array $ranks): void
    {
        $path = $this->profile->dropPath('common_drop_item');
        $existing = $this->commonDrops();
        $header = $existing['header'];

        if ($header === '' && is_file($path)) {
            $raw = file_get_contents($path);

            if ($raw !== false) {
                $lines = explode("\n", str_replace("\r\n", "\n", LocaleText::decode($raw)));
                $header = array_shift($lines) ?? '';
            }
        }

        if ($header === '') {
            throw new \RuntimeException('admin.drops.common_header_missing');
        }

        $this->backupOnce($path);

        $maxRows = 0;

        foreach ($ranks as $rows) {
            $maxRows = max($maxRows, count($rows));
        }

        $lines = [$header];

        for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
            $cells = [];

            foreach ($this->profile->commonRanks() as $rank) {
                $row = $ranks[$rank][$rowIndex] ?? null;

                if ($row === null) {
                    $cells = array_merge($cells, ['', '1', '15', '0', '0', '0']);

                    continue;
                }

                $cells[] = (string) ($row['label'] ?? '');
                $cells[] = (string) ((int) ($row['level_start'] ?? 1));
                $cells[] = (string) ((int) ($row['level_end'] ?? 15));
                $cells[] = (string) ($row['chance'] ?? '0');
                $cells[] = (string) ($row['item_ref'] ?? '0');
                $cells[] = (string) ((int) ($row['one_in'] ?? 0));
            }

            $lines[] = implode("\t", $cells);
        }

        $this->writeRawFile('common_drop_item', implode("\n", $lines) . "\n");
    }

    /**
     * @return list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}>
     */
    private function loadGroupFile(string $key): array
    {
        $path = $this->profile->dropPath($key);

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
     * @param list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}> $groups
     */
    private function writeGroupFile(string $key, array $groups): void
    {
        $this->writeRawFile($key, $this->writer->write($groups));
    }

    private function writeRawFile(string $key, string $contents): void
    {
        $path = $this->profile->dropPath($key);

        if (!is_writable($path) && !is_writable(dirname($path))) {
            throw new \RuntimeException('admin.drops.not_writable');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('admin.drops.write_failed');
        }
    }

    private function backupOnce(string $path): void
    {
        $backup = $path . '.bak';

        if (is_file($backup) || !is_file($path)) {
            return;
        }

        copy($path, $backup);
    }
}
