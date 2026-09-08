<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

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
                    CONVERT(p.name USING utf8mb4) AS proto_name'
            : 'NULL AS proto_locale_name, NULL AS proto_name';
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

        return array_filter($grouped, static fn (array $items): bool => $items !== []);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeItem(array $row): array
    {
        $locale = trim((string) ($row['proto_locale_name'] ?? ''));
        $proto = trim((string) ($row['proto_name'] ?? ''));
        $name = $locale !== '' && strcasecmp($locale, 'Noname') !== 0
            ? $locale
            : ($proto !== '' && strcasecmp($proto, 'Noname') !== 0 ? $proto : '');

        $sockets = [];

        foreach (['socket0', 'socket1', 'socket2'] as $socket) {
            $value = (int) ($row[$socket] ?? 0);

            if ($value > 0) {
                $sockets[] = $value;
            }
        }

        return [
            'id' => (int) $row['id'],
            'window' => (string) $row['window'],
            'pos' => (int) $row['pos'],
            'count' => (int) $row['count'],
            'vnum' => (int) $row['vnum'],
            'name' => $name,
            'sockets' => $sockets,
        ];
    }
}
