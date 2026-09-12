<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Service\Economy\EconomyAnomaly;
use Mt2Cms\Service\Economy\EconomyStats;
use Mt2Cms\Service\Economy\GoldlogHintParser;
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
            $this->economy->upsertYangDaily($day, [
                'player_yang' => $yang['player_yang'],
                'safebox_yang' => $yang['safebox_yang'],
                'guild_yang' => $yang['guild_yang'],
                'money_monster' => $money['MONSTER'],
                'money_drop' => $money['DROP'],
                'money_shop' => $money['SHOP'],
                'money_refine' => $money['REFINE'],
                'money_quest' => $money['QUEST'],
                'money_guild' => $money['GUILD'],
                'money_misc' => $money['MISC'],
                'money_kill' => $money['KILL'],
            ], $now);

            $ingest = $this->ingestGoldlog();
            $result['trades_ingested'] = $ingest['ingested'];
            $result['trades_unmatched'] = $ingest['unmatched'];

            $this->rebuildMarketDaily($day);

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
    private function ingestGoldlog(): array
    {
        $afterDate = $this->economy->getState('goldlog_date') ?? '0000-00-00';
        $afterTime = $this->economy->getState('goldlog_time') ?? '00:00:00';
        $nameMap = $this->scan->itemNameToVnumMap();
        $ingested = 0;
        $unmatched = 0;
        $lastDate = $afterDate;
        $lastTime = $afterTime;

        $rows = $this->scan->goldlogShopBuysAfter($afterDate, $afterTime);

        foreach ($rows as $row) {
            $lastDate = $row['date'];
            $lastTime = $row['time'];
            $parsed = GoldlogHintParser::parse($row['hint']);

            if ($parsed === null) {
                $unmatched++;

                continue;
            }

            $vnum = $nameMap[$parsed['name']] ?? null;

            if ($vnum === null) {
                $unmatched++;

                continue;
            }

            $total = abs((int) $row['what']);
            $unit = GoldlogHintParser::unitPrice($total, $parsed['count']);

            if ($unit === null || $unit < 2) {
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

            $soldAt = $this->goldlogDateTime($row['date'], $row['time']);
            $key = GoldlogHintParser::tradeKey(
                $row['date'],
                $row['time'],
                $row['pid'],
                $row['what'],
                $row['hint'],
            );

            if ($this->economy->insertTrade($key, $vnum, $parsed['count'], $total, $unit, $soldAt, 'goldlog')) {
                $ingested++;
            }
        }

        if ($rows !== []) {
            $this->economy->setState('goldlog_date', $lastDate);
            $this->economy->setState('goldlog_time', $lastTime);
        }

        return ['ingested' => $ingested, 'unmatched' => $unmatched];
    }

    private function rebuildMarketDaily(string $day): void
    {
        $trades = $this->economy->tradesForDay($day);
        /** @var array<int, list<int>> $prices */
        $prices = [];
        /** @var array<int, int> $units */
        $units = [];

        foreach ($trades as $trade) {
            $vnum = $trade['vnum'];
            $prices[$vnum][] = $trade['unit_price'];
            $units[$vnum] = ($units[$vnum] ?? 0) + $trade['count'];
        }

        foreach ($prices as $vnum => $list) {
            $median = EconomyStats::median($list);
            $p25 = EconomyStats::percentile($list, 25);
            $p75 = EconomyStats::percentile($list, 75);

            $this->economy->upsertMarketDaily(
                $day,
                $vnum,
                count($list),
                $units[$vnum] ?? 0,
                $median !== null ? (int) round($median) : null,
                $p25 !== null ? (int) round($p25) : null,
                $p75 !== null ? (int) round($p75) : null,
                'goldlog',
            );
        }
    }

    /**
     * @param list<array{vnum: int, units: int}> $census
     * @return list<array{vnum: int, kind: string, change: float}>
     */
    private function detectAlerts(string $day, array $census): array
    {
        $watch = $this->economy->watchlistMap();
        $day7 = date('Y-m-d', strtotime('-7 days'));
        $day1 = date('Y-m-d', strtotime('-1 day'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $created = [];

        foreach ($census as $row) {
            $vnum = (int) $row['vnum'];
            $unitsNow = (int) $row['units'];
            $units7 = $this->economy->unitsOnDay($vnum, $day7);

            if ($units7 !== null) {
                $supply = EconomyAnomaly::supplyAlert($unitsNow, $units7);

                if ($supply !== null && $this->economy->insertAlert($day, $vnum, $supply['kind'], [
                    'units_now' => $unitsNow,
                    'units_baseline' => $units7,
                    'change' => $supply['change'],
                ])) {
                    $created[] = ['vnum' => $vnum, 'kind' => $supply['kind'], 'change' => $supply['change']];
                }
            }

            $watched = isset($watch[$vnum]);
            $cfg = $watch[$vnum] ?? null;
            $medianNow = $this->economy->medianPriceForDay($vnum, $day);

            if ($medianNow === null) {
                $medianNow = $this->economy->medianPriceForDay($vnum, $day1);
            }

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

            if ($price !== null && $this->economy->insertAlert($day, $vnum, $price['kind'], [
                'median_now' => $medianNow,
                'median_baseline' => $baseline,
                'trades' => $trades,
                'change' => $price['change'],
                'watched' => $watched,
            ])) {
                $created[] = ['vnum' => $vnum, 'kind' => $price['kind'], 'change' => $price['change']];
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
