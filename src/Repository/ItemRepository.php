<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Game\ItemSockets;

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
        $hasProto = $this->schemaTableExists('item_proto');
        $protoSelect = $hasProto
            ? 'CONVERT(p.locale_name USING utf8mb4) AS proto_locale_name,
                    CONVERT(p.name USING utf8mb4) AS proto_name,
                    p.type AS proto_type,
                    p.subtype AS proto_subtype,
                    p.limittype0 AS proto_limit_type,
                    p.value0 AS proto_value0,
                    p.value2 AS proto_value2'
            : 'NULL AS proto_locale_name, NULL AS proto_name,
                    0 AS proto_type, 0 AS proto_subtype,
                    0 AS proto_limit_type, 0 AS proto_value0, 0 AS proto_value2';
        $protoJoin = $hasProto ? 'LEFT JOIN `item_proto` p ON p.vnum = i.vnum' : '';

        $rows = $this->db()->fetchAll(
            'SELECT i.id, i.window, i.pos, i.count, i.vnum,
                    i.socket0, i.socket1, i.socket2,
                    ' . $protoSelect . '
             FROM `item` i
             ' . $protoJoin . '
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

        return $this->attachSocketNames(
            array_filter($grouped, static fn (array $items): bool => $items !== []),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row): array
    {
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
        );

        return [
            'id' => (int) $row['id'],
            'window' => (string) $row['window'],
            'pos' => (int) $row['pos'],
            'count' => (int) $row['count'],
            'vnum' => (int) $row['vnum'],
            'name' => $this->protoName($row),
            'sockets' => $sockets,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function attachSocketNames(array $grouped): array
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

        $names = $this->protoNames($vnums);

        foreach ($grouped as $window => $items) {
            foreach ($items as $index => $item) {
                foreach ($item['sockets'] as $slot => $socket) {
                    if (($socket['kind'] ?? '') !== 'item') {
                        continue;
                    }

                    $name = $names[(int) $socket['value']] ?? '';

                    if ($name === '') {
                        $grouped[$window][$index]['sockets'][$slot]['kind'] = 'raw';
                        continue;
                    }

                    $grouped[$window][$index]['sockets'][$slot]['name'] = $name;
                }
            }
        }

        return $grouped;
    }

    /**
     * @param list<int> $vnums
     * @return array<int, string>
     */
    private function protoNames(array $vnums): array
    {
        $vnums = array_values(array_unique(array_filter($vnums, static fn (int $vnum): bool => $vnum > 0)));

        if ($vnums === [] || !$this->schemaTableExists('item_proto')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($vnums), '?'));
        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    CONVERT(locale_name USING utf8mb4) AS proto_locale_name,
                    CONVERT(name USING utf8mb4) AS proto_name
             FROM `item_proto`
             WHERE vnum IN (' . $placeholders . ')',
            $vnums,
        );

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['vnum']] = $this->protoName($row);
        }

        return $names;
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
