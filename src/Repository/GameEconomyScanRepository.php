<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

/**
 * Read-only aggregations against the game MySQL (player / log).
 */
class GameEconomyScanRepository extends Repository
{
    private const PLAYER_WINDOWS = [
        'INVENTORY',
        'EQUIPMENT',
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
     * @return list<array{
     *   vnum: int,
     *   window: string,
     *   units: int,
     *   stacks: int,
     *   holders: int
     * }>
     */
    public function itemCountsByWindow(): array
    {
        if (!$this->schemaTableExists('item')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT vnum, `window`,
                    SUM(IF(`count` > 0, `count`, 0)) AS units,
                    SUM(IF(`count` > 0, 1, 0)) AS stacks,
                    COUNT(DISTINCT IF(`count` > 0, owner_id, NULL)) AS holders
             FROM `item`
             GROUP BY vnum, `window`',
        );

        return array_map(static function (array $row): array {
            return [
                'vnum' => (int) ($row['vnum'] ?? 0),
                'window' => (string) ($row['window'] ?? ''),
                'units' => (int) ($row['units'] ?? 0),
                'stacks' => (int) ($row['stacks'] ?? 0),
                'holders' => (int) ($row['holders'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Pending item_award rows (not yet claimed into inventory/mall).
     *
     * @return list<array{vnum: int, units: int, stacks: int}>
     */
    public function pendingAwardCounts(): array
    {
        if (!$this->schemaTableExists('item_award')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    SUM(IF(`count` > 0, `count`, 0)) AS units,
                    COUNT(*) AS stacks
             FROM `item_award`
             WHERE taken_time IS NULL OR taken_time = \'0000-00-00 00:00:00\'
             GROUP BY vnum',
        );

        return array_map(static function (array $row): array {
            return [
                'vnum' => (int) ($row['vnum'] ?? 0),
                'units' => (int) ($row['units'] ?? 0),
                'stacks' => (int) ($row['stacks'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{player_yang: int, safebox_yang: int, guild_yang: int}
     */
    public function yangTotals(): array
    {
        $player = 0;
        $safebox = 0;
        $guild = 0;

        if ($this->schemaTableExists('player')) {
            $player = (int) ($this->db()->fetchColumn('SELECT COALESCE(SUM(gold), 0) FROM `player`') ?? 0);
        }

        if ($this->schemaTableExists('safebox')) {
            $safebox = (int) ($this->db()->fetchColumn('SELECT COALESCE(SUM(gold), 0) FROM `safebox`') ?? 0);
        }

        if ($this->schemaTableExists('guild')) {
            $guild = (int) ($this->db()->fetchColumn('SELECT COALESCE(SUM(gold), 0) FROM `guild`') ?? 0);
        }

        return [
            'player_yang' => $player,
            'safebox_yang' => $safebox,
            'guild_yang' => $guild,
        ];
    }

    /**
     * Map lowercase locale_name / name → vnum; ambiguous names omitted.
     *
     * @return array<string, int>
     */
    public function itemNameToVnumMap(): array
    {
        if (!$this->schemaTableExists('item_proto')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    CONVERT(locale_name USING utf8mb4) AS locale_name,
                    CONVERT(name USING utf8mb4) AS name
             FROM `item_proto`',
        );

        /** @var array<string, list<int>> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $vnum = (int) ($row['vnum'] ?? 0);

            if ($vnum < 1) {
                continue;
            }

            foreach ([(string) ($row['locale_name'] ?? ''), (string) ($row['name'] ?? '')] as $raw) {
                $name = trim($raw);

                if ($name === '' || strcasecmp($name, 'Noname') === 0) {
                    continue;
                }

                $name = mb_strtolower($name);
                $buckets[$name] ??= [];

                if (!in_array($vnum, $buckets[$name], true)) {
                    $buckets[$name][] = $vnum;
                }
            }
        }

        $map = [];

        foreach ($buckets as $name => $vnums) {
            if (count($vnums) === 1) {
                $map[$name] = $vnums[0];
            }
        }

        return $map;
    }

    /**
     * @return array{gold: int, shop_buy_price: int}|null
     */
    public function itemNpcPrices(int $vnum): ?array
    {
        if ($vnum < 1 || !$this->schemaTableExists('item_proto')) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT gold, shop_buy_price FROM `item_proto` WHERE vnum = ? LIMIT 1',
            [$vnum],
        );

        if ($row === null) {
            return null;
        }

        return [
            'gold' => (int) ($row['gold'] ?? 0),
            'shop_buy_price' => (int) ($row['shop_buy_price'] ?? 0),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function itemNamesByVnum(array $vnums): array
    {
        $vnums = array_values(array_unique(array_filter(
            array_map('intval', $vnums),
            static fn (int $v): bool => $v > 0,
        )));

        if ($vnums === [] || !$this->schemaTableExists('item_proto')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($vnums), '?'));
        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    CONVERT(locale_name USING utf8mb4) AS locale_name,
                    CONVERT(name USING utf8mb4) AS name
             FROM `item_proto` WHERE vnum IN (' . $placeholders . ')',
            $vnums,
        );

        $map = [];

        foreach ($rows as $row) {
            $vnum = (int) ($row['vnum'] ?? 0);
            $locale = trim((string) ($row['locale_name'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $map[$vnum] = $locale !== '' && strcasecmp($locale, 'Noname') !== 0
                ? $locale
                : $name;
        }

        return $map;
    }

    /**
     * Incremental goldlog rows after cursor (date, time).
     *
     * @param list<string> $howFlags SHOP_BUY, SHOP_SELL, BUY, SELL, EXCHANGE_GIVE, EXCHANGE_TAKE, …
     * @return list<array{date: string, time: string, pid: int, what: int, hint: string, how: string}>
     */
    public function goldlogRowsAfter(string $afterDate, string $afterTime, array $howFlags, int $limit = 5000): array
    {
        if ($howFlags === [] || !$this->logTableExists('goldlog')) {
            return [];
        }

        $limit = max(1, min(20000, $limit));
        $conds = [];
        $params = [];

        foreach ($howFlags as $flag) {
            $conds[] = 'FIND_IN_SET(?, `how`) > 0';
            $params[] = $flag;
        }

        $params[] = $afterDate;
        $params[] = $afterDate;
        $params[] = $afterTime;
        $params[] = $limit;

        $log = $this->db->useDatabase('log');
        $rows = $log->fetchAll(
            'SELECT `date`, `time`, pid, `what`, hint, `how`
             FROM `goldlog`
             WHERE (' . implode(' OR ', $conds) . ')
               AND (`date` > ? OR (`date` = ? AND `time` > ?))
             ORDER BY `date` ASC, `time` ASC
             LIMIT ?',
            $params,
        );

        return array_map(static function (array $row): array {
            return [
                'date' => (string) ($row['date'] ?? ''),
                'time' => (string) ($row['time'] ?? ''),
                'pid' => (int) ($row['pid'] ?? 0),
                'what' => (int) ($row['what'] ?? 0),
                'hint' => (string) ($row['hint'] ?? ''),
                'how' => (string) ($row['how'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * Incremental goldlog player-shop trades after cursor (date, time).
     *
     * @return list<array{date: string, time: string, pid: int, what: int, hint: string, how: string}>
     */
    public function goldlogShopTradesAfter(string $afterDate, string $afterTime, int $limit = 5000): array
    {
        return $this->goldlogRowsAfter($afterDate, $afterTime, ['SHOP_BUY', 'SHOP_SELL'], $limit);
    }

    /**
     * NPC buy/sell from goldlog (not used for market median).
     *
     * @return list<array{date: string, time: string, pid: int, what: int, hint: string, how: string}>
     */
    public function goldlogNpcTradesAfter(string $afterDate, string $afterTime, int $limit = 5000): array
    {
        return $this->goldlogRowsAfter($afterDate, $afterTime, ['BUY', 'SELL'], $limit);
    }

    /**
     * Player–player yang transfers (exchange).
     *
     * @return list<array{date: string, time: string, pid: int, what: int, hint: string, how: string}>
     */
    public function goldlogExchangeAfter(string $afterDate, string $afterTime, int $limit = 5000): array
    {
        return $this->goldlogRowsAfter($afterDate, $afterTime, ['EXCHANGE_GIVE', 'EXCHANGE_TAKE'], $limit);
    }

    /**
     * Incremental ITEM log player-shop sales (SHOP_SELL) after cursor.
     *
     * `what` is the item uid; yang and count live in hint.
     *
     * @return list<array{time: string, who: int, what: int, hint: string, vnum: int}>
     */
    public function itemLogShopSellsAfter(string $afterTime, int $afterUid, int $limit = 5000): array
    {
        if (!$this->logTableExists('log')) {
            return [];
        }

        $limit = max(1, min(20000, $limit));
        $log = $this->db->useDatabase('log');
        $rows = $log->fetchAll(
            'SELECT `time`, who, `what`, hint, vnum
             FROM `log`
             WHERE `type` = \'ITEM\'
               AND `how` = \'SHOP_SELL\'
               AND (`time` > ? OR (`time` = ? AND `what` > ?))
             ORDER BY `time` ASC, `what` ASC
             LIMIT ?',
            [$afterTime, $afterTime, $afterUid, $limit],
        );

        return array_map(static function (array $row): array {
            return [
                'time' => (string) ($row['time'] ?? ''),
                'who' => (int) ($row['who'] ?? 0),
                'what' => (int) ($row['what'] ?? 0),
                'hint' => (string) ($row['hint'] ?? ''),
                'vnum' => (int) ($row['vnum'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * money_log gold sums by type for a calendar day (best-effort; table has no date id).
     *
     * Net per type is gold SUM; created/destroyed split positive vs negative rows.
     *
     * @return array{
     *   by_type: array<string, int>,
     *   created: int,
     *   destroyed: int
     * }
     */
    public function moneyLogSumsForDay(string $day): array
    {
        $byType = [
            'MONSTER' => 0,
            'SHOP' => 0,
            'REFINE' => 0,
            'QUEST' => 0,
            'GUILD' => 0,
            'MISC' => 0,
            'KILL' => 0,
            'DROP' => 0,
        ];
        $created = 0;
        $destroyed = 0;

        if (!$this->logTableExists('money_log')) {
            return ['by_type' => $byType, 'created' => 0, 'destroyed' => 0];
        }

        $log = $this->db->useDatabase('log');
        $rows = $log->fetchAll(
            'SELECT `type`,
                    COALESCE(SUM(gold), 0) AS total,
                    COALESCE(SUM(IF(gold > 0, gold, 0)), 0) AS created,
                    COALESCE(SUM(IF(gold < 0, -gold, 0)), 0) AS destroyed
             FROM `money_log`
             WHERE DATE(`time`) = ?
             GROUP BY `type`',
            [$day],
        );

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');

            if (isset($byType[$type])) {
                $byType[$type] = (int) ($row['total'] ?? 0);
            }

            $created += (int) ($row['created'] ?? 0);
            $destroyed += (int) ($row['destroyed'] ?? 0);
        }

        return [
            'by_type' => $byType,
            'created' => $created,
            'destroyed' => $destroyed,
        ];
    }

    /**
     * Character gold rankings for wealth concentration (player.gold only; no safebox).
     *
     * @return list<array{pid: int, yang: int}>
     */
    public function playerYangRanking(int $limit = 0): array
    {
        if (!$this->schemaTableExists('player')) {
            return [];
        }

        $sql = 'SELECT id AS pid, gold AS yang
                FROM `player`
                WHERE gold > 0
                ORDER BY gold DESC';
        $params = [];

        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = max(1, min(10000, $limit));
        }

        $rows = $this->db()->fetchAll($sql, $params);

        return array_map(static function (array $row): array {
            return [
                'pid' => (int) ($row['pid'] ?? 0),
                'yang' => (int) ($row['yang'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{player_count: int, total_yang: int}
     */
    public function playerYangTotals(): array
    {
        if (!$this->schemaTableExists('player')) {
            return ['player_count' => 0, 'total_yang' => 0];
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS player_count, COALESCE(SUM(gold), 0) AS total_yang FROM `player`',
        );

        return [
            'player_count' => (int) ($row['player_count'] ?? 0),
            'total_yang' => (int) ($row['total_yang'] ?? 0),
        ];
    }

    public function playerGold(int $pid): ?int
    {
        if ($pid < 1 || !$this->schemaTableExists('player')) {
            return null;
        }

        $value = $this->db()->fetchColumn(
            'SELECT gold FROM `player` WHERE id = ? LIMIT 1',
            [$pid],
        );

        return $value !== null ? (int) $value : null;
    }

    private function logTableExists(string $table): bool
    {
        $table = \Mt2Cms\Support\Database::quoteIdentifier($table);

        return $this->db->useDatabase('log')->fetch(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             LIMIT 1',
            ['log', $table],
        ) !== null;
    }

    public static function isPlayerWindow(string $window): bool
    {
        return in_array($window, self::PLAYER_WINDOWS, true);
    }

    public static function isAccountWindow(string $window): bool
    {
        return in_array($window, self::ACCOUNT_WINDOWS, true);
    }
}
