<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Service\Economy\EconomyAnomaly;
use Mt2Cms\Service\Economy\EconomyStats;
use Mt2Cms\Service\Economy\GoldlogHintParser;
use Mt2Cms\Service\Economy\ItemLogHintParser;
use Mt2Cms\Support\Log;

class EconomyTickService
{
    private const LOCK_FILE = 'var/economy-tick.lock';
    private const OUTLIER_BAND = 10.0;

    public function __construct(
        private GameEconomyScanRepository $scan,
        private EconomyRepository $economy,
        private ?DiscordWebhookService $discord = null,
        private string $baseDir = '',
    ) {
        if ($this->baseDir === '') {
            $this->baseDir = defined('BASE_DIR') ? (string) BASE_DIR : dirname(__DIR__, 2);
        }
    }

    /**
     * @return array{
     *   skipped: bool,
     *   census_rows: int,
     *   trades_ingested: int,
     *   trades_unmatched: int,
     *   alerts_created: int
     * }
     */
    public function run(): array
    {
        $result = [
            'skipped' => false,
            'census_rows' => 0,
            'trades_ingested' => 0,
            'trades_unmatched' => 0,
            'alerts_created' => 0,
        ];

        $lock = $this->acquireLock();

        if ($lock === null) {
            $result['skipped'] = true;

            return $result;
        }

        try {
            $now = date('Y-m-d H:i:s');
            $day = date('Y-m-d');

            $census = $this->buildCensus();
            $this->economy->replaceCensus($census, $now, $day);
            $result['census_rows'] = count($census);

            $yang = $this->scan->yangTotals();
            $money = $this->scan->moneyLogSumsForDay($day);
            $byType = $money['by_type'];
            $this->economy->upsertYangDaily($day, [
                'player_yang' => $yang['player_yang'],
                'safebox_yang' => $yang['safebox_yang'],
                'guild_yang' => $yang['guild_yang'],
                'money_monster' => $byType['MONSTER'],
                'money_drop' => $byType['DROP'],
                'money_shop' => $byType['SHOP'],
                'money_refine' => $byType['REFINE'],
                'money_quest' => $byType['QUEST'],
                'money_guild' => $byType['GUILD'],
                'money_misc' => $byType['MISC'],
                'money_kill' => $byType['KILL'],
                'money_created' => $money['created'],
                'money_destroyed' => $money['destroyed'],
            ], $now);

            $this->economy->applyCensusDeltas($day);

            $ingest = $this->ingestMarketTrades();
            $result['trades_ingested'] = $ingest['ingested'];
            $result['trades_unmatched'] = $ingest['unmatched'];

            $this->rebuildMarketDaily();
            $this->snapshotWealth($day, $now);

            $newAlerts = $this->detectAlerts($day, $census);
            $result['alerts_created'] = count($newAlerts);

            if ($newAlerts !== [] && $this->discord !== null) {
                $this->discord->notifyEconomyAlerts($newAlerts);
            }

            $this->economy->setState('last_ok_at', $now);
        } catch (\Throwable $e) {
            Log::error('economy', 'Tick failed', $e);

            throw $e;
        } finally {
            $this->releaseLock($lock);
        }

        return $result;
    }

    /**
     * @return list<array{
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
     * }>
     */
    private function buildCensus(): array
    {
        /** @var array<int, array{
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
         * }> $byVnum
         */
        $byVnum = [];

        foreach ($this->scan->itemCountsByWindow() as $row) {
            $vnum = (int) $row['vnum'];

            if ($vnum < 1) {
                continue;
            }

            if (!isset($byVnum[$vnum])) {
                $byVnum[$vnum] = [
                    'vnum' => $vnum,
                    'units' => 0,
                    'stacks' => 0,
                    'holders_players' => 0,
                    'holders_accounts' => 0,
                    'units_player' => 0,
                    'units_safebox' => 0,
                    'units_mall' => 0,
                    'units_pending' => 0,
                    'stacks_pending' => 0,
                ];
            }

            $units = (int) $row['units'];
            $stacks = (int) $row['stacks'];
            $holders = (int) $row['holders'];
            $window = (string) $row['window'];

            $byVnum[$vnum]['units'] += $units;
            $byVnum[$vnum]['stacks'] += $stacks;

            if (GameEconomyScanRepository::isPlayerWindow($window)) {
                $byVnum[$vnum]['units_player'] += $units;
                $byVnum[$vnum]['holders_players'] += $holders;
            } elseif ($window === 'SAFEBOX') {
                $byVnum[$vnum]['units_safebox'] += $units;
                $byVnum[$vnum]['holders_accounts'] += $holders;
            } elseif ($window === 'MALL') {
                $byVnum[$vnum]['units_mall'] += $units;
                $byVnum[$vnum]['holders_accounts'] += $holders;
            }
        }

        foreach ($this->scan->pendingAwardCounts() as $row) {
            $vnum = (int) $row['vnum'];

            if ($vnum < 1) {
                continue;
            }

            if (!isset($byVnum[$vnum])) {
                $byVnum[$vnum] = [
                    'vnum' => $vnum,
                    'units' => 0,
                    'stacks' => 0,
                    'holders_players' => 0,
                    'holders_accounts' => 0,
                    'units_player' => 0,
                    'units_safebox' => 0,
                    'units_mall' => 0,
                    'units_pending' => 0,
                    'stacks_pending' => 0,
                ];
            }

            $units = (int) $row['units'];
            $stacks = (int) $row['stacks'];
            $byVnum[$vnum]['units_pending'] += $units;
            $byVnum[$vnum]['stacks_pending'] += $stacks;
            $byVnum[$vnum]['units'] += $units;
            $byVnum[$vnum]['stacks'] += $stacks;
        }

        return array_values($byVnum);
    }

    /**
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestMarketTrades(): array
    {
        $item = $this->ingestItemLogShop();
        $gold = $this->ingestGoldlogShop();
        $npc = $this->ingestGoldlogNpc();
        $xfer = $this->ingestGoldlogExchange();

        return [
            'ingested' => $item['ingested'] + $gold['ingested'] + $npc['ingested'] + $xfer['ingested'],
            'unmatched' => $item['unmatched'] + $gold['unmatched'] + $npc['unmatched'] + $xfer['unmatched'],
        ];
    }

    /**
     * Player-shop sales from `log` (this core leaves goldlog empty).
     *
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestItemLogShop(): array
    {
        $afterTime = $this->economy->getState('itemlog_shop_time') ?? '0000-00-00 00:00:00';
        $afterUid = (int) ($this->economy->getState('itemlog_shop_uid') ?? '0');
        $ingested = 0;
        $unmatched = 0;
        $lastTime = $afterTime;
        $lastUid = $afterUid;

        $rows = $this->scan->itemLogShopSellsAfter($afterTime, $afterUid);

        foreach ($rows as $row) {
            $lastTime = $row['time'];
            $lastUid = $row['what'];
            $vnum = (int) $row['vnum'];
            $parsed = ItemLogHintParser::parseShop($row['hint']);

            if ($vnum < 1 || $parsed === null) {
                $unmatched++;

                continue;
            }

            $total = $parsed['yang'];
            $unit = GoldlogHintParser::unitPrice($total, $parsed['count']);

            if ($unit === null || $unit < 1) {
                continue;
            }

            $baseline = $this->economy->baselineMedian(
                $vnum,
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d', strtotime('-1 day')),
            );

            if ($baseline !== null && $baseline > 0) {
                $ratio = $unit / $baseline;

                if ($ratio > self::OUTLIER_BAND || $ratio < (1.0 / self::OUTLIER_BAND)) {
                    continue;
                }
            }

            $key = hash('sha1', $row['time'] . '|' . $row['what'] . '|' . $vnum . '|' . $total . '|SHOP_SELL');
            // SHOP_SELL: who = seller, other_pid = buyer
            $sellerPid = (int) $row['who'];
            $buyerPid = (int) $parsed['other_pid'];

            if ($this->economy->insertTrade(
                $key,
                $vnum,
                $parsed['count'],
                $total,
                $unit,
                $row['time'],
                'itemlog',
                $sellerPid,
                $buyerPid,
                'shop',
            )) {
                $ingested++;
            }
        }

        if ($rows !== []) {
            $this->economy->setState('itemlog_shop_time', $lastTime);
            $this->economy->setState('itemlog_shop_uid', (string) $lastUid);
        }

        return ['ingested' => $ingested, 'unmatched' => $unmatched];
    }

    /**
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestGoldlogShop(): array
    {
        return $this->ingestGoldlogChannel(
            'goldlog_market_date',
            'goldlog_market_time',
            fn (string $d, string $t) => $this->scan->goldlogShopTradesAfter($d, $t),
            'shop',
            true,
        );
    }

    /**
     * NPC BUY/SELL — volume only, excluded from market median.
     *
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestGoldlogNpc(): array
    {
        return $this->ingestGoldlogChannel(
            'goldlog_npc_date',
            'goldlog_npc_time',
            fn (string $d, string $t) => $this->scan->goldlogNpcTradesAfter($d, $t),
            'npc',
            false,
        );
    }

    /**
     * @param callable(string, string): list<array{date: string, time: string, pid: int, what: int, hint: string, how: string}> $fetcher
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestGoldlogChannel(
        string $dateState,
        string $timeState,
        callable $fetcher,
        string $channel,
        bool $applyOutlierFilter,
    ): array {
        $afterDate = $this->economy->getState($dateState) ?? '0000-00-00';
        $afterTime = $this->economy->getState($timeState) ?? '00:00:00';
        $nameMap = $this->scan->itemNameToVnumMap();
        $ingested = 0;
        $unmatched = 0;
        $lastDate = $afterDate;
        $lastTime = $afterTime;

        $rows = $fetcher($afterDate, $afterTime);

        foreach ($rows as $row) {
            $lastDate = $row['date'];
            $lastTime = $row['time'];
            $parsed = GoldlogHintParser::parse($row['hint']);

            if ($parsed === null) {
                $unmatched++;

                continue;
            }

            $vnum = GoldlogHintParser::resolveVnum($parsed['name'], $nameMap);

            if ($vnum === null) {
                $unmatched++;

                continue;
            }

            $total = abs((int) $row['what']);
            $unit = GoldlogHintParser::unitPrice($total, $parsed['count']);

            if ($unit === null || $unit < 1) {
                continue;
            }

            if ($applyOutlierFilter) {
                $baseline = $this->economy->baselineMedian(
                    $vnum,
                    date('Y-m-d', strtotime('-7 days')),
                    date('Y-m-d', strtotime('-1 day')),
                );

                if ($baseline !== null && $baseline > 0) {
                    $ratio = $unit / $baseline;

                    if ($ratio > self::OUTLIER_BAND || $ratio < (1.0 / self::OUTLIER_BAND)) {
                        continue;
                    }
                }
            }

            $how = strtoupper((string) preg_replace('/,.*/', '', $row['how']));
            $sellerPid = null;
            $buyerPid = null;

            if ($channel === 'shop') {
                if (str_contains($how, 'SHOP_SELL')) {
                    $sellerPid = $row['pid'];
                } elseif (str_contains($how, 'SHOP_BUY')) {
                    $buyerPid = $row['pid'];
                }
            } elseif ($channel === 'npc') {
                if (str_contains($how, 'SELL')) {
                    $sellerPid = $row['pid'];
                } else {
                    $buyerPid = $row['pid'];
                }
            }

            $soldAt = $this->goldlogDateTime($row['date'], $row['time']);
            $key = GoldlogHintParser::tradeKey(
                $row['date'],
                $row['time'],
                $row['pid'],
                $row['what'],
                $row['hint'] . '|' . $channel,
            );

            if ($this->economy->insertTrade(
                $key,
                $vnum,
                $parsed['count'],
                $total,
                $unit,
                $soldAt,
                'goldlog',
                $sellerPid,
                $buyerPid,
                $channel,
            )) {
                $ingested++;
            }
        }

        if ($rows !== []) {
            $this->economy->setState($dateState, $lastDate);
            $this->economy->setState($timeState, $lastTime);
        }

        return ['ingested' => $ingested, 'unmatched' => $unmatched];
    }

    /**
     * @return array{ingested: int, unmatched: int}
     */
    private function ingestGoldlogExchange(): array
    {
        $afterDate = $this->economy->getState('goldlog_xfer_date') ?? '0000-00-00';
        $afterTime = $this->economy->getState('goldlog_xfer_time') ?? '00:00:00';
        $ingested = 0;
        $unmatched = 0;
        $lastDate = $afterDate;
        $lastTime = $afterTime;

        $rows = $this->scan->goldlogExchangeAfter($afterDate, $afterTime);

        foreach ($rows as $row) {
            $lastDate = $row['date'];
            $lastTime = $row['time'];
            $yang = abs((int) $row['what']);

            if ($yang < 1) {
                $unmatched++;

                continue;
            }

            $how = strtoupper((string) $row['how']);
            $fromPid = 0;
            $toPid = 0;

            if (str_contains($how, 'EXCHANGE_GIVE')) {
                $fromPid = $row['pid'];
            } elseif (str_contains($how, 'EXCHANGE_TAKE')) {
                $toPid = $row['pid'];
            } else {
                $unmatched++;

                continue;
            }

            $at = $this->goldlogDateTime($row['date'], $row['time']);
            $key = hash('sha1', $row['date'] . '|' . $row['time'] . '|' . $row['pid'] . '|' . $row['what'] . '|' . $how);

            if ($this->economy->insertYangTransfer($key, $fromPid, $toPid, $yang, $at, 'goldlog')) {
                $ingested++;
            }
        }

        if ($rows !== []) {
            $this->economy->setState('goldlog_xfer_date', $lastDate);
            $this->economy->setState('goldlog_xfer_time', $lastTime);
        }

        return ['ingested' => $ingested, 'unmatched' => $unmatched];
    }

    private function rebuildMarketDaily(): void
    {
        $from = date('Y-m-d', strtotime('-90 days'));
        $grouped = $this->economy->tradesGroupedByDaySince($from, 'shop');

        foreach ($grouped as $day => $byVnum) {
            foreach ($byVnum as $vnum => $info) {
                $list = $info['prices'];
                $median = EconomyStats::median($list);
                $p25 = EconomyStats::percentile($list, 25);
                $p75 = EconomyStats::percentile($list, 75);

                $this->economy->upsertMarketDaily(
                    $day,
                    $vnum,
                    count($list),
                    $info['units'],
                    $info['volume_yang'],
                    $info['unique_sellers'],
                    $info['unique_buyers'],
                    $median !== null ? (int) round($median) : null,
                    $p25 !== null ? (int) round($p25) : null,
                    $p75 !== null ? (int) round($p75) : null,
                    $info['source'] !== '' ? $info['source'] : 'itemlog',
                );
            }
        }
    }

    private function snapshotWealth(string $day, string $now): void
    {
        $totals = $this->scan->playerYangTotals();
        $ranking = $this->scan->playerYangRanking(0);
        $concentration = EconomyStats::wealthConcentration(
            array_column($ranking, 'yang'),
            $totals['total_yang'],
        );

        $this->economy->upsertWealthDaily($day, [
            'player_count' => $totals['player_count'],
            'total_yang' => $totals['total_yang'],
            'top1_pct' => $concentration['top1_pct'],
            'top5_pct' => $concentration['top5_pct'],
            'top10_pct' => $concentration['top10_pct'],
        ], $now);

        $top = array_slice($ranking, 0, 50);
        $this->economy->replaceWealthTop($day, $top);
    }

    /**
     * @param list<array{vnum: int, units: int}> $census
     * @return list<array{vnum: int, kind: string, change: float, subject_type?: string, subject_id?: int}>
     */
    private function detectAlerts(string $day, array $census): array
    {
        $watch = $this->economy->watchlistMap();
        $day7 = date('Y-m-d', strtotime('-7 days'));
        $day1 = date('Y-m-d', strtotime('-1 day'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day30 = date('Y-m-d', strtotime('-30 days'));
        $created = [];

        foreach ($census as $row) {
            $vnum = (int) $row['vnum'];
            $unitsNow = (int) $row['units'];
            $units7 = $this->economy->unitsOnDay($vnum, $day7);

            if ($units7 !== null) {
                $supply = EconomyAnomaly::supplyAlert($unitsNow, $units7);

                if ($supply !== null && $this->economy->insertAlert($day, 'item', $vnum, $supply['kind'], [
                    'units_now' => $unitsNow,
                    'units_baseline' => $units7,
                    'change' => $supply['change'],
                ])) {
                    $created[] = [
                        'vnum' => $vnum,
                        'kind' => $supply['kind'],
                        'change' => $supply['change'],
                        'subject_type' => 'item',
                        'subject_id' => $vnum,
                    ];
                }
            }

            $watched = isset($watch[$vnum]);
            $cfg = $watch[$vnum] ?? null;
            $medianNow = $this->economy->latestMedian($vnum);

            $baseline = $this->economy->baselineMedian($vnum, $day7, $day1);
            $trades = $this->economy->tradesCountSince($vnum, $day2);

            $price = EconomyAnomaly::priceAlert(
                $medianNow !== null ? (float) $medianNow : null,
                $baseline,
                $trades,
                $watched,
                isset($cfg['crash_pct']) && $cfg['crash_pct'] !== null ? (float) $cfg['crash_pct'] : null,
                isset($cfg['spike_pct']) && $cfg['spike_pct'] !== null ? (float) $cfg['spike_pct'] : null,
                isset($cfg['min_sample']) ? $cfg['min_sample'] : null,
            );

            if ($price !== null && $this->economy->insertAlert($day, 'item', $vnum, $price['kind'], [
                'median_now' => $medianNow,
                'median_baseline' => $baseline,
                'trades' => $trades,
                'change' => $price['change'],
                'watched' => $watched,
            ])) {
                $created[] = [
                    'vnum' => $vnum,
                    'kind' => $price['kind'],
                    'change' => $price['change'],
                    'subject_type' => 'item',
                    'subject_id' => $vnum,
                ];
            }

            $history = $this->economy->medianSeries($vnum, $day30, $day1);
            $zAlert = EconomyAnomaly::zScoreAlert(
                $medianNow !== null ? (float) $medianNow : null,
                $history,
            );

            if ($zAlert !== null && $this->economy->insertAlert($day, 'item', $vnum, $zAlert['kind'], [
                'median_now' => $medianNow,
                'z_score' => $zAlert['z_score'],
                'change' => $zAlert['change'],
            ])) {
                $created[] = [
                    'vnum' => $vnum,
                    'kind' => $zAlert['kind'],
                    'change' => $zAlert['change'],
                    'subject_type' => 'item',
                    'subject_id' => $vnum,
                ];
            }

            $volNow = $this->economy->tradesCountSince($vnum, $day);
            $volAvg = $this->economy->avgDailyTrades($vnum, $day7, $day1);
            $volAlert = EconomyAnomaly::volumeAlert($volNow, $volAvg);

            if ($volAlert !== null && $this->economy->insertAlert($day, 'item', $vnum, $volAlert['kind'], [
                'trades_now' => $volNow,
                'trades_avg' => $volAvg,
                'change' => $volAlert['change'],
            ])) {
                $created[] = [
                    'vnum' => $vnum,
                    'kind' => $volAlert['kind'],
                    'change' => $volAlert['change'],
                    'subject_type' => 'item',
                    'subject_id' => $vnum,
                ];
            }

            $concentration = $this->economy->tradeConcentration($vnum, $day7, 4);
            $concAlert = EconomyAnomaly::concentrationAlert(
                $concentration['share'],
                $concentration['player_count'],
                $concentration['trades'],
            );

            if ($concAlert !== null && $this->economy->insertAlert($day, 'item', $vnum, $concAlert['kind'], [
                'share' => $concentration['share'],
                'player_count' => $concentration['player_count'],
                'trades' => $concentration['trades'],
                'change' => $concAlert['change'],
            ])) {
                $created[] = [
                    'vnum' => $vnum,
                    'kind' => $concAlert['kind'],
                    'change' => $concAlert['change'],
                    'subject_type' => 'item',
                    'subject_id' => $vnum,
                ];
            }
        }

        foreach ($this->economy->yangVelocityCandidates($day) as $cand) {
            $vel = EconomyAnomaly::yangVelocityAlert(
                $cand['yang_received'],
                $cand['unique_sources'],
                $cand['window_minutes'],
            );

            if ($vel !== null && $this->economy->insertAlert($day, 'player', $cand['pid'], $vel['kind'], [
                'yang_received' => $cand['yang_received'],
                'unique_sources' => $cand['unique_sources'],
                'window_minutes' => $cand['window_minutes'],
                'change' => $vel['change'],
            ])) {
                $created[] = [
                    'vnum' => 0,
                    'kind' => $vel['kind'],
                    'change' => $vel['change'],
                    'subject_type' => 'player',
                    'subject_id' => $cand['pid'],
                ];
            }
        }

        return $created;
    }

    private function goldlogDateTime(string $date, string $time): string
    {
        $date = trim($date);
        $time = trim($time);

        if ($date === '' || $date === '0000-00-00') {
            return date('Y-m-d H:i:s');
        }

        if ($time === '') {
            $time = '00:00:00';
        }

        return $date . ' ' . $time;
    }

    /** @return resource|null */
    private function acquireLock()
    {
        $path = rtrim($this->baseDir, '/') . '/' . self::LOCK_FILE;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $fh = @fopen($path, 'c+');

        if ($fh === false) {
            return null;
        }

        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return null;
        }

        ftruncate($fh, 0);
        fwrite($fh, (string) getmypid());

        return $fh;
    }

    /** @param resource $lock */
    private function releaseLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
