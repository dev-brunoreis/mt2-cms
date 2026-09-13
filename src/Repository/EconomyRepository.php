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
             LEFT JOIN (
                 SELECT m1.vnum, m1.day, m1.median_price, m1.trades
                 FROM item_market_daily m1
                 INNER JOIN (
                     SELECT vnum, MAX(day) AS day FROM item_market_daily GROUP BY vnum
                 ) latest ON latest.vnum = m1.vnum AND latest.day = m1.day
             ) m ON m.vnum = c.vnum
             LEFT JOIN item_census_daily d1 ON d1.vnum = c.vnum AND d1.day = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
             LEFT JOIN item_market_daily m7 ON m7.vnum = c.vnum AND m.day IS NOT NULL
                  AND m7.day = DATE_SUB(m.day, INTERVAL 7 DAY)'
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
             LEFT JOIN (
                 SELECT m1.vnum, m1.day, m1.median_price, m1.trades
                 FROM item_market_daily m1
                 INNER JOIN (
                     SELECT vnum, MAX(day) AS day FROM item_market_daily GROUP BY vnum
                 ) latest ON latest.vnum = m1.vnum AND latest.day = m1.day
             ) m ON m.vnum = c.vnum
             LEFT JOIN item_census_daily d1 ON d1.vnum = c.vnum AND d1.day = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
             LEFT JOIN item_census_daily d7 ON d7.vnum = c.vnum AND d7.day = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             LEFT JOIN item_market_daily m7 ON m7.vnum = c.vnum AND m.day IS NOT NULL
                  AND m7.day = DATE_SUB(m.day, INTERVAL 7 DAY)'
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
     *   money_kill: int,
     *   money_created?: int,
     *   money_destroyed?: int
     * } $row
     */
    public function upsertYangDaily(string $day, array $row, string $capturedAt): void
    {
        $this->db()->execute(
            'INSERT INTO economy_yang_daily
             (day, player_yang, safebox_yang, guild_yang,
              money_monster, money_drop, money_shop, money_refine,
              money_quest, money_guild, money_misc, money_kill,
              money_created, money_destroyed, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                money_created = VALUES(money_created),
                money_destroyed = VALUES(money_destroyed),
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
                (int) ($row['money_created'] ?? 0),
                (int) ($row['money_destroyed'] ?? 0),
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
                    money_quest, money_guild, money_misc, money_kill,
                    money_created, money_destroyed, captured_at
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
                    money_quest, money_guild, money_misc, money_kill,
                    money_created, money_destroyed, captured_at
             FROM economy_yang_daily
             WHERE day = ?
             LIMIT 1',
            [$day],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function yangHistory(int $days = 30): array
    {
        $days = max(1, min(90, $days));

        return $this->db()->fetchAll(
            'SELECT day, player_yang, safebox_yang, guild_yang,
                    money_created, money_destroyed
             FROM economy_yang_daily
             WHERE day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY day ASC',
            [$days],
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
        ?int $sellerPid = null,
        ?int $buyerPid = null,
        string $channel = 'shop',
    ): bool {
        try {
            $this->db()->execute(
                'INSERT INTO item_market_trades
                 (trade_key, vnum, count, price_yang, unit_price, sold_at, source,
                  seller_pid, buyer_pid, channel)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $tradeKey,
                    $vnum,
                    $count,
                    $priceYang,
                    $unitPrice,
                    $soldAt,
                    $source,
                    $sellerPid,
                    $buyerPid,
                    $channel,
                ],
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function insertYangTransfer(
        string $transferKey,
        int $fromPid,
        int $toPid,
        int $yangAmount,
        string $transferredAt,
        string $source = 'goldlog',
    ): bool {
        try {
            $this->db()->execute(
                'INSERT INTO economy_yang_transfers
                 (transfer_key, from_pid, to_pid, yang_amount, transferred_at, source)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$transferKey, $fromPid, $toPid, $yangAmount, $transferredAt, $source],
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function upsertMarketDaily(
        string $day,
        int $vnum,
        int $trades,
        int $units,
        int $volumeYang,
        int $uniqueSellers,
        int $uniqueBuyers,
        ?int $median,
        ?int $p25,
        ?int $p75,
        string $source = 'goldlog',
    ): void {
        $this->db()->execute(
            'INSERT INTO item_market_daily
             (day, vnum, trades, units, volume_yang, unique_sellers, unique_buyers,
              median_price, p25_price, p75_price, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                trades = VALUES(trades),
                units = VALUES(units),
                volume_yang = VALUES(volume_yang),
                unique_sellers = VALUES(unique_sellers),
                unique_buyers = VALUES(unique_buyers),
                median_price = VALUES(median_price),
                p25_price = VALUES(p25_price),
                p75_price = VALUES(p75_price),
                source = VALUES(source)',
            [
                $day,
                $vnum,
                $trades,
                $units,
                $volumeYang,
                $uniqueSellers,
                $uniqueBuyers,
                $median,
                $p25,
                $p75,
                $source,
            ],
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
     * @return array<string, array<int, array{
     *   prices: list<int>,
     *   units: int,
     *   volume_yang: int,
     *   unique_sellers: int,
     *   unique_buyers: int,
     *   source: string
     * }>>
     */
    public function tradesGroupedByDaySince(string $fromDay, string $channel = 'shop'): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT DATE(sold_at) AS day, vnum, unit_price, count, price_yang, source,
                    seller_pid, buyer_pid
             FROM item_market_trades
             WHERE DATE(sold_at) >= ? AND channel = ?
             ORDER BY day ASC, vnum ASC',
            [$fromDay, $channel],
        );

        /** @var array<string, array<int, array{
         *   prices: list<int>,
         *   units: int,
         *   volume_yang: int,
         *   sellers: array<int, true>,
         *   buyers: array<int, true>,
         *   source: string
         * }>> $out
         */
        $out = [];

        foreach ($rows as $row) {
            $day = (string) ($row['day'] ?? '');
            $vnum = (int) ($row['vnum'] ?? 0);

            if ($day === '' || $vnum < 1) {
                continue;
            }

            if (!isset($out[$day][$vnum])) {
                $out[$day][$vnum] = [
                    'prices' => [],
                    'units' => 0,
                    'volume_yang' => 0,
                    'sellers' => [],
                    'buyers' => [],
                    'source' => (string) ($row['source'] ?? 'itemlog'),
                ];
            }

            $out[$day][$vnum]['prices'][] = (int) ($row['unit_price'] ?? 0);
            $out[$day][$vnum]['units'] += (int) ($row['count'] ?? 0);
            $out[$day][$vnum]['volume_yang'] += (int) ($row['price_yang'] ?? 0);
            $out[$day][$vnum]['source'] = (string) ($row['source'] ?? $out[$day][$vnum]['source']);

            $seller = (int) ($row['seller_pid'] ?? 0);
            $buyer = (int) ($row['buyer_pid'] ?? 0);

            if ($seller > 0) {
                $out[$day][$vnum]['sellers'][$seller] = true;
            }

            if ($buyer > 0) {
                $out[$day][$vnum]['buyers'][$buyer] = true;
            }
        }

        $normalized = [];

        foreach ($out as $day => $byVnum) {
            foreach ($byVnum as $vnum => $info) {
                $normalized[$day][$vnum] = [
                    'prices' => $info['prices'],
                    'units' => $info['units'],
                    'volume_yang' => $info['volume_yang'],
                    'unique_sellers' => count($info['sellers']),
                    'unique_buyers' => count($info['buyers']),
                    'source' => $info['source'],
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTrades(int $vnum, int $limit = 25): array
    {
        if ($vnum < 1) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        return $this->db()->fetchAll(
            'SELECT sold_at, count, price_yang, unit_price, source, seller_pid, buyer_pid, channel
             FROM item_market_trades
             WHERE vnum = ?
             ORDER BY sold_at DESC
             LIMIT ?',
            [$vnum, $limit],
        );
    }

    public function latestMedian(int $vnum): ?int
    {
        if ($vnum < 1) {
            return null;
        }

        $value = $this->db()->fetchColumn(
            'SELECT median_price FROM item_market_daily
             WHERE vnum = ?
             ORDER BY day DESC
             LIMIT 1',
            [$vnum],
        );

        return $value !== null ? (int) $value : null;
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
            'SELECT day, units, stacks, units_player, units_safebox, units_mall, units_pending, units_delta
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
            'SELECT day, trades, units, volume_yang, unique_sellers, unique_buyers,
                    median_price, p25_price, p75_price, source
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

    public function insertAlert(string $day, string $subjectType, int $subjectId, string $kind, array $payload): bool
    {
        $vnum = $subjectType === 'item' ? $subjectId : 0;

        try {
            $this->db()->execute(
                'INSERT INTO economy_alerts (day, subject_type, subject_id, vnum, kind, payload)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$day, $subjectType, $subjectId, $vnum, $kind, json_encode($payload, JSON_THROW_ON_ERROR)],
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
            'SELECT id, day, subject_type, subject_id, vnum, kind, payload, created_at
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
            'SELECT id, day, subject_type, subject_id, vnum, kind, payload, created_at, acked_at
             FROM economy_alerts
             WHERE subject_type = \'item\' AND subject_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$vnum, $limit],
        );

        return array_map([$this, 'normalizeAlert'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAlertsForPlayer(int $pid, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT id, day, subject_type, subject_id, vnum, kind, payload, created_at, acked_at
             FROM economy_alerts
             WHERE subject_type = \'player\' AND subject_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$pid, $limit],
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

        $subjectType = (string) ($row['subject_type'] ?? 'item');
        $subjectId = (int) ($row['subject_id'] ?? 0);
        $vnum = (int) ($row['vnum'] ?? 0);

        if ($subjectId < 1 && $vnum > 0) {
            $subjectId = $vnum;
            $subjectType = 'item';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'day' => (string) ($row['day'] ?? ''),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'vnum' => $vnum > 0 ? $vnum : ($subjectType === 'item' ? $subjectId : 0),
            'kind' => (string) ($row['kind'] ?? ''),
            'payload' => $payload,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'acked_at' => isset($row['acked_at']) && $row['acked_at'] !== null
                ? (string) $row['acked_at']
                : null,
        ];
    }

    /**
     * Set units_delta = today.units - yesterday.units for a census day.
     */
    public function applyCensusDeltas(string $day): void
    {
        $this->db()->execute(
            'UPDATE item_census_daily today
             LEFT JOIN item_census_daily yday
               ON yday.vnum = today.vnum AND yday.day = DATE_SUB(?, INTERVAL 1 DAY)
             SET today.units_delta = CAST(today.units AS SIGNED) - CAST(COALESCE(yday.units, today.units) AS SIGNED)
             WHERE today.day = ?',
            [$day, $day],
        );
    }

    /**
     * @return list<array{day: string, volume_yang: int, trades: int}>
     */
    public function marketVolumeByDay(string $fromDay, string $toDay): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT DATE(sold_at) AS day,
                    COALESCE(SUM(price_yang), 0) AS volume_yang,
                    COUNT(*) AS trades
             FROM item_market_trades
             WHERE channel = \'shop\'
               AND DATE(sold_at) BETWEEN ? AND ?
             GROUP BY DATE(sold_at)
             ORDER BY day ASC',
            [$fromDay, $toDay],
        );

        return array_map(static function (array $row): array {
            return [
                'day' => (string) ($row['day'] ?? ''),
                'volume_yang' => (int) ($row['volume_yang'] ?? 0),
                'trades' => (int) ($row['trades'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{volume_yang: int, trades: int, money_created: int, money_destroyed: int}
     */
    public function marketKpis(string $fromDay, string $toDay): array
    {
        $trade = $this->db()->fetch(
            'SELECT COALESCE(SUM(price_yang), 0) AS volume_yang,
                    COUNT(*) AS trades
             FROM item_market_trades
             WHERE channel = \'shop\'
               AND DATE(sold_at) BETWEEN ? AND ?',
            [$fromDay, $toDay],
        );

        $yang = $this->db()->fetch(
            'SELECT COALESCE(SUM(money_created), 0) AS money_created,
                    COALESCE(SUM(money_destroyed), 0) AS money_destroyed
             FROM economy_yang_daily
             WHERE day BETWEEN ? AND ?',
            [$fromDay, $toDay],
        );

        return [
            'volume_yang' => (int) ($trade['volume_yang'] ?? 0),
            'trades' => (int) ($trade['trades'] ?? 0),
            'money_created' => (int) ($yang['money_created'] ?? 0),
            'money_destroyed' => (int) ($yang['money_destroyed'] ?? 0),
        ];
    }

    /**
     * @return list<array{vnum: int, volume_yang: int, trades: int, units: int}>
     */
    public function topSoldByVolume(string $fromDay, string $toDay, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT vnum,
                    COALESCE(SUM(price_yang), 0) AS volume_yang,
                    COUNT(*) AS trades,
                    COALESCE(SUM(count), 0) AS units
             FROM item_market_trades
             WHERE channel = \'shop\'
               AND DATE(sold_at) BETWEEN ? AND ?
             GROUP BY vnum
             ORDER BY volume_yang DESC
             LIMIT ?',
            [$fromDay, $toDay, $limit],
        );

        return array_map(static function (array $row): array {
            return [
                'vnum' => (int) ($row['vnum'] ?? 0),
                'volume_yang' => (int) ($row['volume_yang'] ?? 0),
                'trades' => (int) ($row['trades'] ?? 0),
                'units' => (int) ($row['units'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Largest median price swings vs ~7 days earlier.
     *
     * @return list<array{vnum: int, median_now: int, median_then: int, change: float, trades: int}>
     */
    public function topPriceMovers(string $asOfDay, int $lookbackDays = 7, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $thenDay = date('Y-m-d', strtotime($asOfDay . ' -' . max(1, $lookbackDays) . ' days'));

        $rows = $this->db()->fetchAll(
            'SELECT n.vnum, n.median_price AS median_now, p.median_price AS median_then, n.trades
             FROM item_market_daily n
             INNER JOIN item_market_daily p ON p.vnum = n.vnum AND p.day = ?
             WHERE n.day = ?
               AND n.median_price IS NOT NULL AND p.median_price IS NOT NULL AND p.median_price > 0
               AND n.trades >= 3
             ORDER BY ABS((n.median_price / p.median_price) - 1) DESC
             LIMIT ?',
            [$thenDay, $asOfDay, $limit],
        );

        return array_map(static function (array $row): array {
            $now = (int) ($row['median_now'] ?? 0);
            $then = (int) ($row['median_then'] ?? 0);
            $change = $then > 0 ? ($now / $then) - 1.0 : 0.0;

            return [
                'vnum' => (int) ($row['vnum'] ?? 0),
                'median_now' => $now,
                'median_then' => $then,
                'change' => $change,
                'trades' => (int) ($row['trades'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<float|int>
     */
    public function medianSeries(int $vnum, string $fromDay, string $toDay): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT median_price FROM item_market_daily
             WHERE vnum = ? AND day BETWEEN ? AND ? AND median_price IS NOT NULL
             ORDER BY day ASC',
            [$vnum, $fromDay, $toDay],
        );

        return array_map(static fn (array $r): int => (int) ($r['median_price'] ?? 0), $rows);
    }

    public function avgDailyTrades(int $vnum, string $fromDay, string $toDay): ?float
    {
        $row = $this->db()->fetch(
            'SELECT AVG(trades) AS avg_trades, COUNT(*) AS days
             FROM item_market_daily
             WHERE vnum = ? AND day BETWEEN ? AND ?',
            [$vnum, $fromDay, $toDay],
        );

        if ($row === null || (int) ($row['days'] ?? 0) < 1) {
            return null;
        }

        return (float) ($row['avg_trades'] ?? 0);
    }

    /**
     * Share of shop trades involving the top N distinct player ids (sellers ∪ buyers).
     *
     * @return array{share: float, player_count: int, trades: int}
     */
    public function tradeConcentration(int $vnum, string $sinceDay, int $topPlayers = 4): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT seller_pid, buyer_pid FROM item_market_trades
             WHERE vnum = ? AND channel = \'shop\' AND DATE(sold_at) >= ?',
            [$vnum, $sinceDay],
        );

        $trades = count($rows);

        if ($trades < 1) {
            return ['share' => 0.0, 'player_count' => 0, 'trades' => 0];
        }

        /** @var array<int, int> $counts */
        $counts = [];

        foreach ($rows as $row) {
            foreach ([(int) ($row['seller_pid'] ?? 0), (int) ($row['buyer_pid'] ?? 0)] as $pid) {
                if ($pid > 0) {
                    $counts[$pid] = ($counts[$pid] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $top = array_slice(array_keys($counts), 0, max(1, $topPlayers), true);
        $involved = 0;

        foreach ($rows as $row) {
            $s = (int) ($row['seller_pid'] ?? 0);
            $b = (int) ($row['buyer_pid'] ?? 0);

            if (in_array($s, $top, true) || in_array($b, $top, true)) {
                $involved++;
            }
        }

        return [
            'share' => $involved / $trades,
            'player_count' => count($top),
            'trades' => $trades,
        ];
    }

    /**
     * Players who received a lot of yang via transfers in a short window today.
     * goldlog EXCHANGE_TAKE rows set to_pid; from_pid is often 0, so source count uses row count.
     *
     * @return list<array{pid: int, yang_received: int, unique_sources: int, window_minutes: int}>
     */
    public function yangVelocityCandidates(string $day): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT to_pid AS pid,
                    COALESCE(SUM(yang_amount), 0) AS yang_received,
                    COUNT(*) AS unique_sources,
                    TIMESTAMPDIFF(MINUTE, MIN(transferred_at), MAX(transferred_at)) AS window_minutes
             FROM economy_yang_transfers
             WHERE to_pid > 0 AND DATE(transferred_at) = ?
             GROUP BY to_pid
             HAVING yang_received >= 100000000 AND unique_sources >= 5',
            [$day],
        );

        return array_map(static function (array $row): array {
            return [
                'pid' => (int) ($row['pid'] ?? 0),
                'yang_received' => (int) ($row['yang_received'] ?? 0),
                'unique_sources' => (int) ($row['unique_sources'] ?? 0),
                'window_minutes' => max(0, (int) ($row['window_minutes'] ?? 0)),
            ];
        }, $rows);
    }

    /**
     * @param array{player_count: int, total_yang: int, top1_pct: float, top5_pct: float, top10_pct: float} $row
     */
    public function upsertWealthDaily(string $day, array $row, string $capturedAt): void
    {
        $this->db()->execute(
            'INSERT INTO economy_wealth_daily
             (day, player_count, total_yang, top1_pct, top5_pct, top10_pct, captured_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                player_count = VALUES(player_count),
                total_yang = VALUES(total_yang),
                top1_pct = VALUES(top1_pct),
                top5_pct = VALUES(top5_pct),
                top10_pct = VALUES(top10_pct),
                captured_at = VALUES(captured_at)',
            [
                $day,
                (int) ($row['player_count'] ?? 0),
                (int) ($row['total_yang'] ?? 0),
                (float) ($row['top1_pct'] ?? 0),
                (float) ($row['top5_pct'] ?? 0),
                (float) ($row['top10_pct'] ?? 0),
                $capturedAt,
            ],
        );
    }

    /**
     * @param list<array{pid: int, yang: int}> $top
     */
    public function replaceWealthTop(string $day, array $top): void
    {
        $this->db()->execute('DELETE FROM economy_wealth_top_daily WHERE day = ?', [$day]);

        $rank = 1;

        foreach ($top as $row) {
            $pid = (int) ($row['pid'] ?? 0);

            if ($pid < 1) {
                continue;
            }

            $this->db()->execute(
                'INSERT INTO economy_wealth_top_daily (day, rank_pos, pid, yang)
                 VALUES (?, ?, ?, ?)',
                [$day, $rank, $pid, (int) ($row['yang'] ?? 0)],
            );
            $rank++;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestWealth(): ?array
    {
        return $this->db()->fetch(
            'SELECT day, player_count, total_yang, top1_pct, top5_pct, top10_pct, captured_at
             FROM economy_wealth_daily
             ORDER BY day DESC
             LIMIT 1',
        );
    }

    /**
     * @return list<array{rank_pos: int, pid: int, yang: int}>
     */
    public function wealthTopForDay(string $day, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->db()->fetchAll(
            'SELECT rank_pos, pid, yang
             FROM economy_wealth_top_daily
             WHERE day = ?
             ORDER BY rank_pos ASC
             LIMIT ?',
            [$day, $limit],
        );

        return array_map(static function (array $row): array {
            return [
                'rank_pos' => (int) ($row['rank_pos'] ?? 0),
                'pid' => (int) ($row['pid'] ?? 0),
                'yang' => (int) ($row['yang'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<array{pid: int, volume_yang: int, trades: int}>
     */
    public function topSellers(string $fromDay, string $toDay, int $limit = 20): array
    {
        return $this->topTradeParty('seller_pid', $fromDay, $toDay, $limit);
    }

    /**
     * @return list<array{pid: int, volume_yang: int, trades: int}>
     */
    public function topBuyers(string $fromDay, string $toDay, int $limit = 20): array
    {
        return $this->topTradeParty('buyer_pid', $fromDay, $toDay, $limit);
    }

    /**
     * @return list<array{pid: int, volume_yang: int, trades: int}>
     */
    private function topTradeParty(string $column, string $fromDay, string $toDay, int $limit): array
    {
        $column = $column === 'buyer_pid' ? 'buyer_pid' : 'seller_pid';
        $limit = max(1, min(100, $limit));

        $rows = $this->db()->fetchAll(
            "SELECT {$column} AS pid,
                    COALESCE(SUM(price_yang), 0) AS volume_yang,
                    COUNT(*) AS trades
             FROM item_market_trades
             WHERE channel = 'shop'
               AND {$column} IS NOT NULL AND {$column} > 0
               AND DATE(sold_at) BETWEEN ? AND ?
             GROUP BY {$column}
             ORDER BY volume_yang DESC
             LIMIT ?",
            [$fromDay, $toDay, $limit],
        );

        return array_map(static function (array $row): array {
            return [
                'pid' => (int) ($row['pid'] ?? 0),
                'volume_yang' => (int) ($row['volume_yang'] ?? 0),
                'trades' => (int) ($row['trades'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function playerShopTrades(int $pid, int $limit = 50): array
    {
        if ($pid < 1) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->db()->fetchAll(
            'SELECT sold_at, vnum, count, price_yang, unit_price, seller_pid, buyer_pid, channel
             FROM item_market_trades
             WHERE channel = \'shop\' AND (seller_pid = ? OR buyer_pid = ?)
             ORDER BY sold_at DESC
             LIMIT ?',
            [$pid, $pid, $limit],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function playerTransfers(int $pid, int $limit = 50): array
    {
        if ($pid < 1) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->db()->fetchAll(
            'SELECT transferred_at, from_pid, to_pid, yang_amount, source
             FROM economy_yang_transfers
             WHERE from_pid = ? OR to_pid = ?
             ORDER BY transferred_at DESC
             LIMIT ?',
            [$pid, $pid, $limit],
        );
    }

    public function unitsDeltaOnDay(int $vnum, string $day): ?int
    {
        $value = $this->db()->fetchColumn(
            'SELECT units_delta FROM item_census_daily WHERE vnum = ? AND day = ? LIMIT 1',
            [$vnum, $day],
        );

        return $value !== null ? (int) $value : null;
    }
}
