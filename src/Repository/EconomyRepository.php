<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\EconomyGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

/**
 * CMS snapshot tables for economy census / market / alerts.
 */
class EconomyRepository extends Repository implements ProvidesAdminGrid
{
    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_census c
             LEFT JOIN economy_watchlist w ON w.vnum = c.vnum
             LEFT JOIN item_market_daily m ON m.vnum = c.vnum AND m.day = CURDATE()
             LEFT JOIN item_census_daily d1 ON d1.vnum = c.vnum AND d1.day = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
             LEFT JOIN item_market_daily m7 ON m7.vnum = c.vnum AND m7.day = DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
            . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, EconomyGrid::definition()->sortMap(), 'c.units DESC');

        $rows = $this->db()->fetchAll(
            'SELECT c.vnum, c.units, c.stacks, c.holders_players, c.holders_accounts,
                    c.units_player, c.units_safebox, c.units_mall, c.units_pending,
                    c.captured_at,
                    (w.vnum IS NOT NULL) AS watched,
                    m.median_price, m.trades AS price_trades,
                    d1.units AS units_1d, d7.units AS units_7d,
                    m7.median_price AS median_price_7d
             FROM item_census c
             LEFT JOIN economy_watchlist w ON w.vnum = c.vnum
             LEFT JOIN item_market_daily m ON m.vnum = c.vnum AND m.day = CURDATE()
             LEFT JOIN item_census_daily d1 ON d1.vnum = c.vnum AND d1.day = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
             LEFT JOIN item_census_daily d7 ON d7.vnum = c.vnum AND d7.day = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             LEFT JOIN item_market_daily m7 ON m7.vnum = c.vnum AND m7.day = DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
            . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        return array_map(static function (array $row): array {
            $units = (int) ($row['units'] ?? 0);
            $units1d = $row['units_1d'] !== null ? (int) $row['units_1d'] : null;
            $units7d = $row['units_7d'] !== null ? (int) $row['units_7d'] : null;
            $median = $row['median_price'] !== null ? (int) $row['median_price'] : null;
            $median7d = $row['median_price_7d'] !== null ? (int) $row['median_price_7d'] : null;

            return [
                'id' => (int) ($row['vnum'] ?? 0),
                'vnum' => (int) ($row['vnum'] ?? 0),
                'units' => $units,
                'stacks' => (int) ($row['stacks'] ?? 0),
                'holders_players' => (int) ($row['holders_players'] ?? 0),
                'holders_accounts' => (int) ($row['holders_accounts'] ?? 0),
                'units_player' => (int) ($row['units_player'] ?? 0),
                'units_safebox' => (int) ($row['units_safebox'] ?? 0),
                'units_mall' => (int) ($row['units_mall'] ?? 0),
                'units_pending' => (int) ($row['units_pending'] ?? 0),
                'captured_at' => (string) ($row['captured_at'] ?? ''),
                'watched' => (int) ($row['watched'] ?? 0),
                'median_price' => $median,
                'price_trades' => (int) ($row['price_trades'] ?? 0),
                'delta_1d_pct' => self::pctDelta($units, $units1d),
                'delta_7d_pct' => self::pctDelta($units, $units7d),
                'price_delta_7d_pct' => self::pctDelta($median, $median7d),
            ];
        }, $rows);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $map = [
            'vnum' => ['sql' => 'c.vnum', 'op' => 'eq'],
            'watched' => ['sql' => '(w.vnum IS NOT NULL)', 'op' => 'eq'],
        ];

        [$where, $params] = GridSql::where($query, $map);

        if ($query->filter('movers') === '1') {
            $extra = '(ABS(COALESCE(c.units, 0) - COALESCE(d1.units, c.units)) > GREATEST(COALESCE(d1.units, 0) * 0.2, 10)
                       OR (m.median_price IS NOT NULL AND m7.median_price IS NOT NULL
                           AND ABS(m.median_price - m7.median_price) > GREATEST(m7.median_price * 0.2, 1)))';

            if ($where === '') {
                $where = ' WHERE ' . $extra;
            } else {
                $where .= ' AND ' . $extra;
            }
        }

        return [$where, $params];
    }

    private static function pctDelta(int|float|null $now, int|float|null $baseline): ?float
    {
        if ($now === null || $baseline === null || (float) $baseline == 0.0) {
            return null;
        }

        return round((((float) $now / (float) $baseline) - 1.0) * 100.0, 1);
    }

    /**
     * Replace current census snapshot and upsert today's daily rows.
     *
     * @param list<array{
     *   vnum: int,
     *   units: int,
     *   stacks: int,
     *   holders_players: int,
     *   holders_accounts: int,
     *   units_player: int,
     *   units_safebox: int,
     *   units_mall: int,
     *   units_pending: int,
     *   stacks_pending: int
     * }> $rows
     */
    public function replaceCensus(array $rows, string $capturedAt, string $day): void
    {
        $this->db()->beginTransaction();

        try {
            $this->db()->execute('DELETE FROM item_census');

            foreach ($rows as $row) {
                $vnum = (int) ($row['vnum'] ?? 0);

                if ($vnum < 1) {
                    continue;
                }

                $params = [
                    $vnum,
                    (int) ($row['units'] ?? 0),
                    (int) ($row['stacks'] ?? 0),
                    (int) ($row['holders_players'] ?? 0),
                    (int) ($row['holders_accounts'] ?? 0),
                    (int) ($row['units_player'] ?? 0),
                    (int) ($row['units_safebox'] ?? 0),
                    (int) ($row['units_mall'] ?? 0),
                    (int) ($row['units_pending'] ?? 0),
                    (int) ($row['stacks_pending'] ?? 0),
                    $capturedAt,
                ];

                $this->db()->execute(
                    'INSERT INTO item_census
                     (vnum, units, stacks, holders_players, holders_accounts,
                      units_player, units_safebox, units_mall, units_pending, stacks_pending, captured_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $params,
                );

                $this->db()->execute(
                    'INSERT INTO item_census_daily
                     (day, vnum, units, stacks, holders_players, holders_accounts,
                      units_player, units_safebox, units_mall, units_pending)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        units = VALUES(units),
                        stacks = VALUES(stacks),
                        holders_players = VALUES(holders_players),
                        holders_accounts = VALUES(holders_accounts),
                        units_player = VALUES(units_player),
                        units_safebox = VALUES(units_safebox),
                        units_mall = VALUES(units_mall),
                        units_pending = VALUES(units_pending)',
                    [
                        $day,
                        $vnum,
                        (int) ($row['units'] ?? 0),
                        (int) ($row['stacks'] ?? 0),
                        (int) ($row['holders_players'] ?? 0),
                        (int) ($row['holders_accounts'] ?? 0),
                        (int) ($row['units_player'] ?? 0),
                        (int) ($row['units_safebox'] ?? 0),
                        (int) ($row['units_mall'] ?? 0),
                        (int) ($row['units_pending'] ?? 0),
                    ],
                );
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            throw $e;
        }
    }

    /**
     * @param array{
     *   player_yang: int,
     *   safebox_yang: int,
     *   guild_yang: int,
     *   money_monster: int,
     *   money_drop: int,
     *   money_shop: int,
     *   money_refine: int,
     *   money_quest: int,
     *   money_guild: int,
     *   money_misc: int,
     *   money_kill: int
     * } $row
     */
    public function upsertYangDaily(string $day, array $row, string $capturedAt): void
    {
        $this->db()->execute(
            'INSERT INTO economy_yang_daily
             (day, player_yang, safebox_yang, guild_yang,
              money_monster, money_drop, money_shop, money_refine,
              money_quest, money_guild, money_misc, money_kill, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                player_yang = VALUES(player_yang),
                safebox_yang = VALUES(safebox_yang),
                guild_yang = VALUES(guild_yang),
                money_monster = VALUES(money_monster),
                money_drop = VALUES(money_drop),
                money_shop = VALUES(money_shop),
                money_refine = VALUES(money_refine),
                money_quest = VALUES(money_quest),
                money_guild = VALUES(money_guild),
                money_misc = VALUES(money_misc),
                money_kill = VALUES(money_kill),
                captured_at = VALUES(captured_at)',
            [
                $day,
                (int) ($row['player_yang'] ?? 0),
                (int) ($row['safebox_yang'] ?? 0),
                (int) ($row['guild_yang'] ?? 0),
                (int) ($row['money_monster'] ?? 0),
                (int) ($row['money_drop'] ?? 0),
                (int) ($row['money_shop'] ?? 0),
                (int) ($row['money_refine'] ?? 0),
                (int) ($row['money_quest'] ?? 0),
                (int) ($row['money_guild'] ?? 0),
                (int) ($row['money_misc'] ?? 0),
                (int) ($row['money_kill'] ?? 0),
                $capturedAt,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestYang(): ?array
    {
        return $this->db()->fetch(
            'SELECT day, player_yang, safebox_yang, guild_yang,
                    money_monster, money_drop, money_shop, money_refine,
                    money_quest, money_guild, money_misc, money_kill, captured_at
             FROM economy_yang_daily
             ORDER BY day DESC
             LIMIT 1',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function yangForDay(string $day): ?array
    {
        return $this->db()->fetch(
            'SELECT day, player_yang, safebox_yang, guild_yang,
                    money_monster, money_drop, money_shop, money_refine,
                    money_quest, money_guild, money_misc, money_kill, captured_at
             FROM economy_yang_daily
             WHERE day = ?
             LIMIT 1',
            [$day],
        );
    }

    public function insertTrade(
        string $tradeKey,
        int $vnum,
        int $count,
        int $priceYang,
        int $unitPrice,
        string $soldAt,
        string $source = 'goldlog',
    ): bool {
        try {
            $this->db()->execute(
                'INSERT INTO item_market_trades
                 (trade_key, vnum, count, price_yang, unit_price, sold_at, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$tradeKey, $vnum, $count, $priceYang, $unitPrice, $soldAt, $source],
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * @param list<int> $unitPrices
     */
    public function upsertMarketDaily(
        string $day,
        int $vnum,
        int $trades,
        int $units,
        ?int $median,
        ?int $p25,
        ?int $p75,
        string $source = 'goldlog',
    ): void {
        $this->db()->execute(
            'INSERT INTO item_market_daily
             (day, vnum, trades, units, median_price, p25_price, p75_price, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                trades = VALUES(trades),
                units = VALUES(units),
                median_price = VALUES(median_price),
                p25_price = VALUES(p25_price),
                p75_price = VALUES(p75_price),
                source = VALUES(source)',
            [$day, $vnum, $trades, $units, $median, $p25, $p75, $source],
        );
    }

    /**
     * @return list<array{vnum: int, unit_price: int, count: int}>
     */
    public function tradesForDay(string $day): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT vnum, unit_price, count
             FROM item_market_trades
             WHERE DATE(sold_at) = ?',
            [$day],
        );

        return array_map(static function (array $row): array {
            return [
                'vnum' => (int) ($row['vnum'] ?? 0),
                'unit_price' => (int) ($row['unit_price'] ?? 0),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCensus(int $vnum): ?array
    {
        if ($vnum < 1) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT vnum, units, stacks, holders_players, holders_accounts,
                    units_player, units_safebox, units_mall, units_pending, stacks_pending, captured_at
             FROM item_census
             WHERE vnum = ?
             LIMIT 1',
            [$vnum],
        );

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function censusHistory(int $vnum, int $days = 30): array
    {
        $days = max(1, min(90, $days));

        return $this->db()->fetchAll(
            'SELECT day, units, stacks, units_player, units_safebox, units_mall, units_pending
             FROM item_census_daily
             WHERE vnum = ? AND day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY day ASC',
            [$vnum, $days],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function marketHistory(int $vnum, int $days = 30): array
    {
        $days = max(1, min(90, $days));

        return $this->db()->fetchAll(
            'SELECT day, trades, units, median_price, p25_price, p75_price, source
             FROM item_market_daily
             WHERE vnum = ? AND day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY day ASC',
            [$vnum, $days],
        );
    }

    public function medianPriceForDay(int $vnum, string $day): ?int
    {
        $value = $this->db()->fetchColumn(
            'SELECT median_price FROM item_market_daily WHERE vnum = ? AND day = ? LIMIT 1',
            [$vnum, $day],
        );

        return $value !== null ? (int) $value : null;
    }

    public function tradesCountSince(int $vnum, string $sinceDay): int
    {
        return (int) ($this->db()->fetchColumn(
            'SELECT COALESCE(SUM(trades), 0) FROM item_market_daily
             WHERE vnum = ? AND day >= ?',
            [$vnum, $sinceDay],
        ) ?? 0);
    }

    /**
     * Average of daily medians over a window (for baseline).
     */
    public function baselineMedian(int $vnum, string $fromDay, string $toDay): ?float
    {
        $rows = $this->db()->fetchAll(
            'SELECT median_price FROM item_market_daily
             WHERE vnum = ? AND day BETWEEN ? AND ? AND median_price IS NOT NULL',
            [$vnum, $fromDay, $toDay],
        );

        if ($rows === []) {
            return null;
        }

        $sum = 0.0;

        foreach ($rows as $row) {
            $sum += (float) ($row['median_price'] ?? 0);
        }

        return $sum / count($rows);
    }

    public function unitsOnDay(int $vnum, string $day): ?int
    {
        $value = $this->db()->fetchColumn(
            'SELECT units FROM item_census_daily WHERE vnum = ? AND day = ? LIMIT 1',
            [$vnum, $day],
        );

        return $value !== null ? (int) $value : null;
    }

    /**
     * @return list<int>
     */
    public function allCensusVnums(): array
    {
        $rows = $this->db()->fetchAll('SELECT vnum FROM item_census');

        return array_map(static fn (array $r): int => (int) $r['vnum'], $rows);
    }

    /**
     * @return array<int, array{crash_pct: ?int, spike_pct: ?int, min_sample: ?int}>
     */
    public function watchlistMap(): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT vnum, crash_pct, spike_pct, min_sample FROM economy_watchlist',
        );
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['vnum']] = [
                'crash_pct' => $row['crash_pct'] !== null ? (int) $row['crash_pct'] : null,
                'spike_pct' => $row['spike_pct'] !== null ? (int) $row['spike_pct'] : null,
                'min_sample' => $row['min_sample'] !== null ? (int) $row['min_sample'] : null,
            ];
        }

        return $map;
    }

    public function isWatched(int $vnum): bool
    {
        return $this->db()->fetchColumn(
            'SELECT 1 FROM economy_watchlist WHERE vnum = ? LIMIT 1',
            [$vnum],
        ) !== null;
    }

    public function watch(int $vnum, ?int $crashPct = null, ?int $spikePct = null, ?int $minSample = null): void
    {
        if ($vnum < 1) {
            return;
        }

        $this->db()->execute(
            'INSERT INTO economy_watchlist (vnum, crash_pct, spike_pct, min_sample)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                crash_pct = VALUES(crash_pct),
                spike_pct = VALUES(spike_pct),
                min_sample = VALUES(min_sample)',
            [$vnum, $crashPct, $spikePct, $minSample],
        );
    }

    public function unwatch(int $vnum): bool
    {
        return $this->db()->execute(
            'DELETE FROM economy_watchlist WHERE vnum = ?',
            [$vnum],
        ) > 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function insertAlert(string $day, int $vnum, string $kind, array $payload): bool
    {
        try {
            $this->db()->execute(
                'INSERT INTO economy_alerts (day, vnum, kind, payload)
                 VALUES (?, ?, ?, ?)',
                [$day, $vnum, $kind, json_encode($payload, JSON_THROW_ON_ERROR)],
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUnackedAlerts(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT id, day, vnum, kind, payload, created_at
             FROM economy_alerts
             WHERE acked_at IS NULL
             ORDER BY created_at DESC
             LIMIT ?',
            [$limit],
        );

        return array_map([$this, 'normalizeAlert'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAlertsForVnum(int $vnum, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT id, day, vnum, kind, payload, created_at, acked_at
             FROM economy_alerts
             WHERE vnum = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$vnum, $limit],
        );

        return array_map([$this, 'normalizeAlert'], $rows);
    }

    public function ackAlert(int $id): bool
    {
        return $this->db()->execute(
            'UPDATE economy_alerts SET acked_at = NOW() WHERE id = ? AND acked_at IS NULL',
            [$id],
        ) > 0;
    }

    public function getState(string $key): ?string
    {
        $value = $this->db()->fetchColumn(
            'SELECT state_value FROM economy_tick_state WHERE state_key = ? LIMIT 1',
            [$key],
        );

        return $value !== null ? (string) $value : null;
    }

    public function setState(string $key, string $value): void
    {
        $this->db()->execute(
            'INSERT INTO economy_tick_state (state_key, state_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)',
            [$key, $value],
        );
    }

    public function lastOkAt(): ?string
    {
        return $this->getState('last_ok_at');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeAlert(array $row): array
    {
        $payload = [];

        try {
            $decoded = json_decode((string) ($row['payload'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            $payload = is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            $payload = [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'day' => (string) ($row['day'] ?? ''),
            'vnum' => (int) ($row['vnum'] ?? 0),
            'kind' => (string) ($row['kind'] ?? ''),
            'payload' => $payload,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'acked_at' => isset($row['acked_at']) && $row['acked_at'] !== null
                ? (string) $row['acked_at']
                : null,
        ];
    }
}
