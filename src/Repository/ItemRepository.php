<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Game\ItemDescCatalog;
use Mt2Cms\Game\ItemSockets;
use Mt2Cms\Game\ItemStats;
use Mt2Cms\Support\Database;

class ItemRepository extends Repository
{
    private const CHARACTER_WINDOWS = [
        'EQUIPMENT',
        'INVENTORY',
        'DRAGON_SOUL_INVENTORY',
        'BELT_INVENTORY',
    ];

    private const ACCOUNT_WINDOWS = [
        'SAFEBOX',
        'MALL',
    ];

    public static function isAccountWindow(string $window): bool
    {
        return in_array($window, self::ACCOUNT_WINDOWS, true);
    }

    public function __construct(
        Database $db = new Database(),
        private ?ItemDescCatalog $descriptions = null,
        private ?ItemStats $itemStats = null,
    ) {
        parent::__construct($db);
    }

    protected function database(): string
    {
        return 'player';
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function forCharacter(int $playerId): array
    {
        return $this->groupedByWindow($playerId, self::CHARACTER_WINDOWS);
    }

    /**
     * Equipment window only (no inventory bags / safebox) for public profiles.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function equipmentForCharacter(int $playerId): array
    {
        return $this->groupedByWindow($playerId, ['EQUIPMENT']);
    }

    /**
     * Safebox gold/size plus SAFEBOX/MALL item rows (owner_id is the account).
     * Never selects the safebox password.
     *
     * @return array{
     *   gold: int,
     *   size: int,
     *   has_row: bool,
     *   items: array<string, list<array<string, mixed>>>
     * }|null
     */
    public function safeboxForAccount(int $accountId): ?array
    {
        if ($accountId < 1) {
            return null;
        }

        $box = null;

        if ($this->schemaTableExists('safebox')) {
            $box = $this->db()->fetch(
                'SELECT gold, size FROM `safebox` WHERE account_id = ?',
                [$accountId],
            );
        }

        $items = $this->groupedByWindow($accountId, self::ACCOUNT_WINDOWS);

        if ($box === null && $items === []) {
            return null;
        }

        return [
            'gold' => (int) ($box['gold'] ?? 0),
            'size' => (int) ($box['size'] ?? 0),
            'has_row' => $box !== null,
            'items' => $items,
        ];
    }

    /**
     * Single item instance by unique id, regardless of window.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id < 1 || !$this->schemaTableExists('item')) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT i.id, i.owner_id, i.window, i.pos, i.count, i.vnum,
                    i.socket0, i.socket1, i.socket2,
                    i.attrtype0, i.attrvalue0, i.attrtype1, i.attrvalue1,
                    i.attrtype2, i.attrvalue2, i.attrtype3, i.attrvalue3,
                    i.attrtype4, i.attrvalue4, i.attrtype5, i.attrvalue5,
                    i.attrtype6, i.attrvalue6,
                    ' . $this->protoSelect() . '
             FROM `item` i
             ' . $this->protoJoin() . '
             WHERE i.id = ?
             LIMIT 1',
            [$id],
        );

        if ($row === null) {
            return null;
        }

        $grouped = $this->attachSocketDetails(['_' => [$this->normalizeItem($row)]]);

        return $grouped['_'][0] ?? null;
    }

    /**
     * @param list<string> $windows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupedByWindow(int $ownerId, array $windows): array
    {
        if ($ownerId < 1 || !$this->schemaTableExists('item')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($windows), '?'));
        $params = [$ownerId, ...$windows];

        $rows = $this->db()->fetchAll(
            'SELECT i.id, i.owner_id, i.window, i.pos, i.count, i.vnum,
                    i.socket0, i.socket1, i.socket2,
                    i.attrtype0, i.attrvalue0, i.attrtype1, i.attrvalue1,
                    i.attrtype2, i.attrvalue2, i.attrtype3, i.attrvalue3,
                    i.attrtype4, i.attrvalue4, i.attrtype5, i.attrvalue5,
                    i.attrtype6, i.attrvalue6,
                    ' . $this->protoSelect() . '
             FROM `item` i
             ' . $this->protoJoin() . '
             WHERE i.owner_id = ?
               AND i.window IN (' . $placeholders . ')
             ORDER BY i.window ASC, i.pos ASC, i.id ASC',
            $params,
        );

        $grouped = [];

        foreach ($windows as $window) {
            $grouped[$window] = [];
        }

        foreach ($rows as $row) {
            $window = (string) $row['window'];
            $grouped[$window][] = $this->normalizeItem($row);
        }

        return $this->attachSocketDetails(
            array_filter($grouped, static fn (array $items): bool => $items !== []),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row): array
    {
        $applies = [
            ['type' => (int) ($row['proto_apply_type0'] ?? 0), 'value' => (int) ($row['proto_apply_value0'] ?? 0)],
            ['type' => (int) ($row['proto_apply_type1'] ?? 0), 'value' => (int) ($row['proto_apply_value1'] ?? 0)],
            ['type' => (int) ($row['proto_apply_type2'] ?? 0), 'value' => (int) ($row['proto_apply_value2'] ?? 0)],
        ];
        $sockets = ItemSockets::describe(
            (int) ($row['proto_type'] ?? 0),
            (int) ($row['proto_subtype'] ?? 0),
            (int) ($row['proto_limit_type'] ?? 0),
            (int) ($row['proto_value0'] ?? 0),
            (int) ($row['proto_value2'] ?? 0),
            [
                (int) ($row['socket0'] ?? 0),
                (int) ($row['socket1'] ?? 0),
                (int) ($row['socket2'] ?? 0),
            ],
            (int) ($row['vnum'] ?? 0),
            $applies,
            $this->itemStats,
        );
        $tooltip = $this->itemStats?->describe(
            (int) ($row['proto_type'] ?? 0),
            (int) ($row['proto_subtype'] ?? 0),
            (int) ($row['proto_limit_type'] ?? 0),
            (int) ($row['proto_limit_value'] ?? 0),
            [
                (int) ($row['proto_value0'] ?? 0),
                (int) ($row['proto_value1'] ?? 0),
                (int) ($row['proto_value2'] ?? 0),
                (int) ($row['proto_value3'] ?? 0),
                (int) ($row['proto_value4'] ?? 0),
                (int) ($row['proto_value5'] ?? 0),
            ],
            $applies,
            [
                ['type' => (int) ($row['attrtype0'] ?? 0), 'value' => (int) ($row['attrvalue0'] ?? 0)],
                ['type' => (int) ($row['attrtype1'] ?? 0), 'value' => (int) ($row['attrvalue1'] ?? 0)],
                ['type' => (int) ($row['attrtype2'] ?? 0), 'value' => (int) ($row['attrvalue2'] ?? 0)],
                ['type' => (int) ($row['attrtype3'] ?? 0), 'value' => (int) ($row['attrvalue3'] ?? 0)],
                ['type' => (int) ($row['attrtype4'] ?? 0), 'value' => (int) ($row['attrvalue4'] ?? 0)],
                ['type' => (int) ($row['attrtype5'] ?? 0), 'value' => (int) ($row['attrvalue5'] ?? 0)],
                ['type' => (int) ($row['attrtype6'] ?? 0), 'value' => (int) ($row['attrvalue6'] ?? 0)],
            ],
            $row['proto_antiflag'] ?? 0,
        ) ?? ['stats' => [], 'applies' => [], 'bonuses' => [], 'special_title' => false, 'wearable' => null];

        return [
            'id' => (int) $row['id'],
            'owner_id' => (int) ($row['owner_id'] ?? 0),
            'window' => (string) $row['window'],
            'pos' => (int) $row['pos'],
            'count' => (int) $row['count'],
            'vnum' => (int) $row['vnum'],
            'name' => $this->protoName($row),
            'size' => max(1, min(3, (int) ($row['proto_size'] ?? 1))),
            'description' => $this->descriptions?->description((int) $row['vnum']) ?? '',
            'stats' => $tooltip['stats'],
            'applies' => $tooltip['applies'],
            'bonuses' => $tooltip['bonuses'],
            'special_title' => (bool) ($tooltip['special_title'] ?? false),
            'wearable' => $tooltip['wearable'] ?? null,
            'sockets' => $sockets,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function attachSocketDetails(array $grouped): array
    {
        $vnums = [];

        foreach ($grouped as $items) {
            foreach ($items as $item) {
                foreach ($item['sockets'] as $socket) {
                    if (($socket['kind'] ?? '') === 'item') {
                        $vnums[] = (int) $socket['value'];
                    }
                }
            }
        }

        $protos = $this->socketProtos($vnums);

        foreach ($grouped as $window => $items) {
            foreach ($items as $index => $item) {
                foreach ($item['sockets'] as $slot => $socket) {
                    if (($socket['kind'] ?? '') !== 'item') {
                        continue;
                    }

                    $vnum = (int) $socket['value'];
                    $proto = $protos[$vnum] ?? null;
                    $name = $proto['name'] ?? '';

                    if ($name === '') {
                        $grouped[$window][$index]['sockets'][$slot]['kind'] = 'raw';
                        continue;
                    }

                    $grouped[$window][$index]['sockets'][$slot]['name'] = $name;

                    if (($socket['applies'] ?? []) !== []) {
                        continue;
                    }

                    $grouped[$window][$index]['sockets'][$slot]['applies'] = $this->itemStats?->applyEntries([
                        [
                            'type' => (int) ($proto['apply_type'] ?? 0),
                            'value' => (int) ($proto['apply_value'] ?? 0),
                        ],
                    ]) ?? [];
                }
            }
        }

        return $grouped;
    }

    /**
     * @param list<int> $vnums
     * @return array<int, array{name: string, apply_type: int, apply_value: int}>
     */
    private function socketProtos(array $vnums): array
    {
        $vnums = array_values(array_unique(array_filter($vnums, static fn (int $vnum): bool => $vnum > 0)));

        if ($vnums === [] || !$this->schemaTableExists('item_proto')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($vnums), '?'));
        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    CONVERT(locale_name USING utf8mb4) AS proto_locale_name,
                    CONVERT(name USING utf8mb4) AS proto_name,
                    applytype0 AS apply_type,
                    applyvalue0 AS apply_value
             FROM `item_proto`
             WHERE vnum IN (' . $placeholders . ')',
            $vnums,
        );

        $protos = [];

        foreach ($rows as $row) {
            $protos[(int) $row['vnum']] = [
                'name' => $this->protoName($row),
                'apply_type' => (int) ($row['apply_type'] ?? 0),
                'apply_value' => (int) ($row['apply_value'] ?? 0),
            ];
        }

        return $protos;
    }

    private function protoSelect(): string
    {
        if (!$this->schemaTableExists('item_proto')) {
            return 'NULL AS proto_locale_name, NULL AS proto_name,
                    0 AS proto_type, 0 AS proto_subtype,
                    0 AS proto_limit_type, 0 AS proto_value0, 0 AS proto_value1,
                    0 AS proto_value2, 0 AS proto_value3, 0 AS proto_value4, 0 AS proto_value5,
                    0 AS proto_apply_type0, 0 AS proto_apply_value0,
                    0 AS proto_apply_type1, 0 AS proto_apply_value1,
                    0 AS proto_apply_type2, 0 AS proto_apply_value2,
                    0 AS proto_limit_value,
                    1 AS proto_size,
                    0 AS proto_antiflag';
        }

        return 'CONVERT(p.locale_name USING utf8mb4) AS proto_locale_name,
                CONVERT(p.name USING utf8mb4) AS proto_name,
                p.type AS proto_type,
                p.subtype AS proto_subtype,
                p.limittype0 AS proto_limit_type,
                p.value0 AS proto_value0,
                p.value1 AS proto_value1,
                p.value2 AS proto_value2,
                p.value3 AS proto_value3,
                p.value4 AS proto_value4,
                p.value5 AS proto_value5,
                p.applytype0 AS proto_apply_type0,
                p.applyvalue0 AS proto_apply_value0,
                p.applytype1 AS proto_apply_type1,
                p.applyvalue1 AS proto_apply_value1,
                p.applytype2 AS proto_apply_type2,
                p.applyvalue2 AS proto_apply_value2,
                p.limitvalue0 AS proto_limit_value,
                p.size AS proto_size,
                p.antiflag AS proto_antiflag';
    }

    private function protoJoin(): string
    {
        return $this->schemaTableExists('item_proto')
            ? 'LEFT JOIN `item_proto` p ON p.vnum = i.vnum'
            : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function protoName(array $row): string
    {
        $locale = trim((string) ($row['proto_locale_name'] ?? ''));
        $proto = trim((string) ($row['proto_name'] ?? ''));

        if ($locale !== '' && strcasecmp($locale, 'Noname') !== 0) {
            return $locale;
        }

        if ($proto !== '' && strcasecmp($proto, 'Noname') !== 0) {
            return $proto;
        }

        return '';
    }
}
