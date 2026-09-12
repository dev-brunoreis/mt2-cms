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
     * Map exact locale_name / name → vnum; ambiguous names omitted.
     *
     * @return array<string, int>
     */
    public function itemNameToVnumMap(): array
    {
        if (!$this->schemaTableExists('item_proto')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT vnum, name, locale_name FROM `item_proto`',
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
            'SELECT vnum, locale_name, name FROM `item_proto` WHERE vnum IN (' . $placeholders . ')',
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
     * Incremental goldlog SHOP_BUY rows after cursor (date, time).
     *
     * @return list<array{date: string, time: string, pid: int, what: int, hint: string}>
     */
    public function goldlogShopBuysAfter(string $afterDate, string $afterTime, int $limit = 5000): array
    {
        if (!$this->logTableExists('goldlog')) {
            return [];
        }

        $limit = max(1, min(20000, $limit));
        $log = $this->db->useDatabase('log');
        $rows = $log->fetchAll(
            'SELECT `date`, `time`, pid, `what`, hint
             FROM `goldlog`
             WHERE FIND_IN_SET(\'SHOP_BUY\', `how`) > 0
               AND (`date` > ? OR (`date` = ? AND `time` > ?))
             ORDER BY `date` ASC, `time` ASC
             LIMIT ?',
            [$afterDate, $afterDate, $afterTime, $limit],
        );

        return array_map(static function (array $row): array {
            return [
                'date' => (string) ($row['date'] ?? ''),
                'time' => (string) ($row['time'] ?? ''),
                'pid' => (int) ($row['pid'] ?? 0),
                'what' => (int) ($row['what'] ?? 0),
                'hint' => (string) ($row['hint'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * money_log gold sums by type for a calendar day (best-effort; table has no date id).
     *
     * @return array<string, int>
     */
    public function moneyLogSumsForDay(string $day): array
    {
        $out = [
            'MONSTER' => 0,
            'SHOP' => 0,
            'REFINE' => 0,
            'QUEST' => 0,
            'GUILD' => 0,
            'MISC' => 0,
            'KILL' => 0,
            'DROP' => 0,
        ];

        if (!$this->logTableExists('money_log')) {
            return $out;
        }

        $log = $this->db->useDatabase('log');
        $rows = $log->fetchAll(
            'SELECT `type`, COALESCE(SUM(gold), 0) AS total
             FROM `money_log`
             WHERE DATE(`time`) = ?
             GROUP BY `type`',
            [$day],
        );

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');

            if (isset($out[$type])) {
                $out[$type] = (int) ($row['total'] ?? 0);
            }
        }

        return $out;
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
