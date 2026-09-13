<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\EconomyGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\DropFileService;
use Mt2Cms\Service\Economy\EconomyAnomaly;
use Mt2Cms\Theme\ThemeEngine;

class AdminEconomyController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private EconomyRepository $economy,
        private GameEconomyScanRepository $scan,
        private DropFileService $drops,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = EconomyGrid::definition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->economy->countForGrid($q),
            fn ($q) => $this->decorateGridRows($this->economy->listForGrid($q)),
        );

        $range = $this->parseRange((string) ($_GET['range'] ?? '7d'));
        $kpis = $this->economy->marketKpis($range['from'], $range['to']);
        $prev = $this->economy->marketKpis($range['prev_from'], $range['prev_to']);

        $lastOk = $this->economy->lastOkAt();
        $stale = $lastOk === null || strtotime($lastOk) < (time() - 7200);
        $yang = $this->economy->latestYang();
        $yangCompare = $this->economy->yangForDay($range['prev_to']);
        $alerts = $this->decorateAlerts($this->economy->listUnackedAlerts(20));

        $totalYang = 0;

        if ($yang !== null) {
            $totalYang = (int) $yang['player_yang']
                + (int) $yang['safebox_yang']
                + (int) $yang['guild_yang'];
        }

        $prevTotalYang = 0;

        if ($yangCompare !== null) {
            $prevTotalYang = (int) $yangCompare['player_yang']
                + (int) $yangCompare['safebox_yang']
                + (int) $yangCompare['guild_yang'];
        }

        $chartDays = match ($range['key']) {
            'today' => 7,
            '30d' => 30,
            default => 7,
        };
        $yangHistory = $this->economy->yangHistory($chartDays);
        $yangValues = [];
        $netValues = [];
        $yangFrom = '';
        $yangTo = '';

        foreach ($yangHistory as $row) {
            $yangValues[] = (int) $row['player_yang']
                + (int) $row['safebox_yang']
                + (int) $row['guild_yang'];
            $netValues[] = (int) ($row['money_created'] ?? 0) - (int) ($row['money_destroyed'] ?? 0);
            $day = (string) ($row['day'] ?? '');

            if ($yangFrom === '') {
                $yangFrom = $day;
            }

            $yangTo = $day;
        }

        $volumeRows = $this->economy->marketVolumeByDay(
            date('Y-m-d', strtotime('-' . ($chartDays - 1) . ' days')),
            date('Y-m-d'),
        );
        $volumeValues = [];
        $volumeFrom = '';
        $volumeTo = '';

        foreach ($volumeRows as $row) {
            $volumeValues[] = (int) $row['volume_yang'];
            $day = (string) ($row['day'] ?? '');

            if ($volumeFrom === '') {
                $volumeFrom = $day;
            }

            $volumeTo = $day;
        }

        $yangChart = $this->dayChart($yangValues, 560, 120);
        $volumeChart = $this->dayChart($volumeValues, 560, 120);
        $netChart = $this->dayChart($netValues, 560, 120);

        $topSold = $this->economy->topSoldByVolume($range['from'], $range['to'], 10);
        $movers = $this->economy->topPriceMovers($range['to'], 7, 10);
        $itemNames = $this->scan->itemNamesByVnum(array_merge(
            array_column($topSold, 'vnum'),
            array_column($movers, 'vnum'),
        ));

        foreach ($topSold as $i => $row) {
            $topSold[$i]['item_name'] = $itemNames[(int) $row['vnum']] ?? ('#' . $row['vnum']);
        }

        foreach ($movers as $i => $row) {
            $movers[$i]['item_name'] = $itemNames[(int) $row['vnum']] ?? ('#' . $row['vnum']);
            $movers[$i]['change_pct'] = round(((float) $row['change']) * 100.0, 1);
        }

        return $this->adminView('economy', 'pages/economy.twig', [
            'title' => $this->t('admin.economy.title'),
            'pageLead' => $this->t('admin.economy.lead'),
            'grid' => $grid,
            'tickStale' => $stale,
            'lastOkAt' => $lastOk,
            'yang' => $yang,
            'totalYang' => $totalYang,
            'alerts' => $alerts,
            'range' => $range['key'],
            'rangeOptions' => ['today', '7d', '30d'],
            'kpis' => [
                'total_yang' => $totalYang,
                'total_yang_delta' => $this->deltaRatio($totalYang, $prevTotalYang),
                'volume_yang' => $kpis['volume_yang'],
                'volume_yang_delta' => $this->deltaRatio($kpis['volume_yang'], $prev['volume_yang']),
                'trades' => $kpis['trades'],
                'trades_delta' => $this->deltaRatio($kpis['trades'], $prev['trades']),
                'money_created' => $kpis['money_created'],
                'money_destroyed' => $kpis['money_destroyed'],
                'money_net' => $kpis['money_created'] - $kpis['money_destroyed'],
                'money_net_delta' => $this->deltaRatio(
                    $kpis['money_created'] - $kpis['money_destroyed'],
                    $prev['money_created'] - $prev['money_destroyed'],
                ),
            ],
            'yangChart' => $yangChart,
            'yangFrom' => $yangFrom,
            'yangTo' => $yangTo,
            'volumeChart' => $volumeChart,
            'volumeFrom' => $volumeFrom,
            'volumeTo' => $volumeTo,
            'netChart' => $netChart,
            'netFrom' => $yangFrom,
            'netTo' => $yangTo,
            'chartDays' => $chartDays,
            'topSold' => $topSold,
            'movers' => $movers,
            'playersHref' => AdminPaths::gameEconomyPlayers(),
        ]);
    }

    public function players(): Response
    {
        $range = $this->parseRange((string) ($_GET['range'] ?? '7d'));
        $wealth = $this->economy->latestWealth();
        $topYang = $wealth !== null
            ? $this->economy->wealthTopForDay((string) $wealth['day'], 50)
            : [];
        $sellers = $this->economy->topSellers($range['from'], $range['to'], 20);
        $buyers = $this->economy->topBuyers($range['from'], $range['to'], 20);

        $pids = array_unique(array_merge(
            array_column($topYang, 'pid'),
            array_column($sellers, 'pid'),
            array_column($buyers, 'pid'),
        ));
        $names = $this->players->namesByIds($pids);

        foreach ($topYang as $i => $row) {
            $topYang[$i]['name'] = $names[(int) $row['pid']] ?? ('#' . $row['pid']);
        }

        foreach ($sellers as $i => $row) {
            $sellers[$i]['name'] = $names[(int) $row['pid']] ?? ('#' . $row['pid']);
        }

        foreach ($buyers as $i => $row) {
            $buyers[$i]['name'] = $names[(int) $row['pid']] ?? ('#' . $row['pid']);
        }

        return $this->adminView('economy', 'pages/economy-players.twig', [
            'title' => $this->t('admin.economy.players_title'),
            'pageLead' => $this->t('admin.economy.players_lead'),
            'backHref' => AdminPaths::gameEconomy(),
            'range' => $range['key'],
            'rangeOptions' => ['today', '7d', '30d'],
            'wealth' => $wealth,
            'topYang' => $topYang,
            'sellers' => $sellers,
            'buyers' => $buyers,
        ]);
    }

    public function playerShow(string $id): Response
    {
        $pid = (int) $id;

        if ($pid < 1) {
            $this->flash('error', $this->t('admin.economy.player_not_found'));

            return $this->redirect(AdminPaths::gameEconomyPlayers());
        }

        $names = $this->players->namesByIds([$pid]);
        $name = $names[$pid] ?? null;

        if ($name === null || $name === '') {
            $this->flash('error', $this->t('admin.economy.player_not_found'));

            return $this->redirect(AdminPaths::gameEconomyPlayers());
        }

        $gold = $this->scan->playerGold($pid);
        $trades = $this->economy->playerShopTrades($pid, 40);
        $transfers = $this->economy->playerTransfers($pid, 40);
        $alerts = $this->economy->listAlertsForPlayer($pid);

        $vnums = array_column($trades, 'vnum');
        $itemNames = $this->scan->itemNamesByVnum($vnums);
        $relatedPids = [];

        foreach ($trades as $t) {
            $relatedPids[] = (int) ($t['seller_pid'] ?? 0);
            $relatedPids[] = (int) ($t['buyer_pid'] ?? 0);
        }

        foreach ($transfers as $t) {
            $relatedPids[] = (int) ($t['from_pid'] ?? 0);
            $relatedPids[] = (int) ($t['to_pid'] ?? 0);
        }

        $relatedNames = $this->players->namesByIds($relatedPids);

        foreach ($trades as $i => $t) {
            $trades[$i]['item_name'] = $itemNames[(int) $t['vnum']] ?? ('#' . $t['vnum']);
            $trades[$i]['seller_name'] = $relatedNames[(int) ($t['seller_pid'] ?? 0)] ?? null;
            $trades[$i]['buyer_name'] = $relatedNames[(int) ($t['buyer_pid'] ?? 0)] ?? null;
        }

        foreach ($transfers as $i => $t) {
            $transfers[$i]['from_name'] = $relatedNames[(int) ($t['from_pid'] ?? 0)] ?? null;
            $transfers[$i]['to_name'] = $relatedNames[(int) ($t['to_pid'] ?? 0)] ?? null;
        }

        return $this->adminView('economy', 'pages/economy-player.twig', [
            'title' => $this->t('admin.economy.player_title', ['name' => $name]),
            'pageLead' => $this->t('admin.economy.player_lead'),
            'backHref' => AdminPaths::gameEconomyPlayers(),
            'pid' => $pid,
            'playerName' => $name,
            'gold' => $gold,
            'trades' => $trades,
            'transfers' => $transfers,
            'alerts' => $alerts,
            'characterHref' => '/admin/game/characters/' . $pid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            EconomyGrid::definition()->spec(),
            '/admin/game/economy',
            [
                'watch' => function (int $vnum): bool {
                    if ($vnum < 1) {
                        return false;
                    }

                    $this->economy->watch($vnum);
                    $this->audit('economy.watch', 'economy_item', $vnum);

                    return true;
                },
                'unwatch' => function (int $vnum): bool {
                    if (!$this->economy->unwatch($vnum)) {
                        return false;
                    }

                    $this->audit('economy.unwatch', 'economy_item', $vnum);

                    return true;
                },
            ],
            'economy',
            'admin.economy.mass_done',
            'game/economy/mass',
        );
    }

    public function show(string $id): Response
    {
        $vnum = (int) $id;

        if ($vnum < 1) {
            $this->flash('error', $this->t('admin.economy.not_found'));

            return $this->redirect('/admin/game/economy');
        }

        $census = $this->economy->findCensus($vnum);
        $names = $this->scan->itemNamesByVnum([$vnum]);
        $name = $names[$vnum] ?? ('#' . $vnum);
        $npc = $this->scan->itemNpcPrices($vnum);
        $history = $this->economy->censusHistory($vnum, 30);
        $market = $this->economy->marketHistory($vnum, 30);
        $watched = $this->economy->isWatched($vnum);
        $watchMap = $this->economy->watchlistMap();
        $watchCfg = $watchMap[$vnum] ?? null;
        $alerts = $this->economy->listAlertsForVnum($vnum);
        $drops = $this->drops->sourcesForItemVnum($vnum);
        $trades = $this->economy->recentTrades($vnum, 25);

        $pids = [];

        foreach ($trades as $t) {
            $pids[] = (int) ($t['seller_pid'] ?? 0);
            $pids[] = (int) ($t['buyer_pid'] ?? 0);
        }

        $playerNames = $this->players->namesByIds($pids);

        foreach ($trades as $i => $t) {
            $seller = (int) ($t['seller_pid'] ?? 0);
            $buyer = (int) ($t['buyer_pid'] ?? 0);
            $trades[$i]['seller_name'] = $seller > 0 ? ($playerNames[$seller] ?? ('#' . $seller)) : null;
            $trades[$i]['buyer_name'] = $buyer > 0 ? ($playerNames[$buyer] ?? ('#' . $buyer)) : null;
        }

        $unitsNow = (int) ($census['units'] ?? 0);
        $units7 = $this->economy->unitsOnDay($vnum, date('Y-m-d', strtotime('-7 days')));
        $supplyChange = null;

        if ($units7 !== null && $units7 > 0) {
            $supplyChange = ($unitsNow / $units7) - 1.0;
        }

        $unitsDelta = $this->economy->unitsDeltaOnDay($vnum, date('Y-m-d'));
        $medianNow = $this->economy->latestMedian($vnum);
        $priceBaseline = $this->economy->baselineMedian(
            $vnum,
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d', strtotime('-1 day')),
        );
        $priceChange = null;

        if ($medianNow !== null && $priceBaseline !== null && $priceBaseline > 0) {
            $priceChange = ($medianNow / $priceBaseline) - 1.0;
        }

        $adviceKey = EconomyAnomaly::dropAdvice($supplyChange, $priceChange);

        $supplyValues = [];
        $supplyFrom = '';
        $supplyTo = '';

        foreach ($history as $row) {
            $supplyValues[] = (int) ($row['units'] ?? 0);
            $day = (string) ($row['day'] ?? '');

            if ($supplyFrom === '') {
                $supplyFrom = $day;
            }

            $supplyTo = $day;
        }

        $priceValues = [];
        $priceFrom = '';
        $priceTo = '';

        foreach ($market as $row) {
            if ($row['median_price'] === null) {
                continue;
            }

            $priceValues[] = (int) $row['median_price'];
            $day = (string) ($row['day'] ?? '');

            if ($priceFrom === '') {
                $priceFrom = $day;
            }

            $priceTo = $day;
        }

        $supplyChart = $this->dayChart($supplyValues, 560, 120);
        $priceChart = $this->dayChart($priceValues, 560, 120);

        return $this->adminView('economy', 'pages/economy-item.twig', [
            'title' => $this->t('admin.economy.item_title', ['name' => $name]),
            'pageLead' => $this->t('admin.economy.item_lead'),
            'backHref' => '/admin/game/economy',
            'vnum' => $vnum,
            'itemName' => $name,
            'census' => $census,
            'npc' => $npc,
            'history' => $history,
            'market' => $market,
            'trades' => $trades,
            'medianNow' => $medianNow,
            'watched' => $watched,
            'watchCfg' => $watchCfg,
            'alerts' => $alerts,
            'drops' => $drops,
            'adviceKey' => $adviceKey,
            'supplyChange' => $supplyChange,
            'priceChange' => $priceChange,
            'unitsDelta' => $unitsDelta,
            'supplyChart' => $supplyChart,
            'supplyFrom' => $supplyFrom,
            'supplyTo' => $supplyTo,
            'priceChart' => $priceChart,
            'priceFrom' => $priceFrom,
            'priceTo' => $priceTo,
            'formId' => 'admin-economy-watch-form',
            'saveLabel' => $this->t('admin.economy.save_watch'),
        ]);
    }

    public function saveWatch(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game/economy/edit')) {
            return $redirect;
        }

        $vnum = (int) $id;

        if ($vnum < 1) {
            $this->flash('error', $this->t('admin.economy.not_found'));

            return $this->redirect('/admin/game/economy');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/economy/' . $vnum);
        }

        $action = (string) ($_POST['watch_action'] ?? 'watch');

        if ($action === 'unwatch') {
            $this->economy->unwatch($vnum);
            $this->audit('economy.unwatch', 'economy_item', $vnum);
            $this->flash('success', $this->t('admin.economy.unwatched'));
        } else {
            $crash = $this->optionalPct($_POST['crash_pct'] ?? null);
            $spike = $this->optionalPct($_POST['spike_pct'] ?? null);
            $min = $this->optionalInt($_POST['min_sample'] ?? null);
            $this->economy->watch($vnum, $crash, $spike, $min);
            $this->auditChange('economy.watch', 'economy_item', $vnum, [], [
                'crash_pct' => $crash,
                'spike_pct' => $spike,
                'min_sample' => $min,
            ]);
            $this->flash('success', $this->t('admin.economy.watched_saved'));
        }

        return $this->redirect('/admin/game/economy/' . $vnum);
    }

    public function ackAlert(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game/economy/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/economy');
        }

        $alertId = (int) $id;

        if ($alertId > 0 && $this->economy->ackAlert($alertId)) {
            $this->audit('economy.alert_ack', 'economy_alert', $alertId);
            $this->flash('success', $this->t('admin.economy.alert_acked'));
        }

        $back = trim((string) ($_POST['back'] ?? '/admin/game/economy'));

        if ($back === '' || !str_starts_with($back, '/admin/game/economy')) {
            $back = '/admin/game/economy';
        }

        return $this->redirect($back);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorateGridRows(array $rows): array
    {
        $names = $this->scan->itemNamesByVnum(array_column($rows, 'vnum'));

        foreach ($rows as $i => $row) {
            $vnum = (int) ($row['vnum'] ?? 0);
            $rows[$i]['item_name'] = $names[$vnum] ?? ('#' . $vnum);
            $rows[$i]['delta_1d_pct'] = $this->formatPct($row['delta_1d_pct'] ?? null);
            $rows[$i]['delta_7d_pct'] = $this->formatPct($row['delta_7d_pct'] ?? null);
            $rows[$i]['price_delta_7d_pct'] = $this->formatPct($row['price_delta_7d_pct'] ?? null);
            $rows[$i]['watched'] = (string) ((int) ($row['watched'] ?? 0));
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private function decorateAlerts(array $alerts): array
    {
        $itemIds = [];
        $playerIds = [];

        foreach ($alerts as $alert) {
            if (($alert['subject_type'] ?? 'item') === 'player') {
                $playerIds[] = (int) $alert['subject_id'];
            } else {
                $itemIds[] = (int) ($alert['vnum'] ?: $alert['subject_id']);
            }
        }

        $itemNames = $this->scan->itemNamesByVnum($itemIds);
        $playerNames = $this->players->namesByIds($playerIds);

        foreach ($alerts as $i => $alert) {
            if (($alert['subject_type'] ?? 'item') === 'player') {
                $pid = (int) $alert['subject_id'];
                $alerts[$i]['label'] = $playerNames[$pid] ?? ('#' . $pid);
                $alerts[$i]['href'] = AdminPaths::gameEconomyPlayer($pid);
            } else {
                $vnum = (int) ($alert['vnum'] ?: $alert['subject_id']);
                $alerts[$i]['label'] = $itemNames[$vnum] ?? ('#' . $vnum);
                $alerts[$i]['href'] = '/admin/game/economy/' . $vnum;
            }
        }

        return $alerts;
    }

    /**
     * @return array{key: string, from: string, to: string, prev_from: string, prev_to: string}
     */
    private function parseRange(string $raw): array
    {
        $key = in_array($raw, ['today', '7d', '30d'], true) ? $raw : '7d';
        $to = date('Y-m-d');

        $days = match ($key) {
            'today' => 0,
            '30d' => 29,
            default => 6,
        };

        $from = date('Y-m-d', strtotime($to . ' -' . $days . ' days'));
        $span = $days + 1;
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($span - 1) . ' days'));

        return [
            'key' => $key,
            'from' => $from,
            'to' => $to,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
        ];
    }

    private function deltaRatio(int|float $now, int|float $prev): ?float
    {
        if ((float) $prev == 0.0) {
            return null;
        }

        return ((float) $now / (float) $prev) - 1.0;
    }

    /**
     * @param list<int|float> $values
     * @return array{points: string, area: string, min_label: string, max_label: string}
     */
    private function dayChart(array $values, int $width = 560, int $height = 120): array
    {
        $empty = ['points' => '', 'area' => '', 'min_label' => '', 'max_label' => ''];

        if (count($values) < 2) {
            return $empty;
        }

        $min = min($values);
        $max = max($values);
        $span = $max - $min;

        if ($span == 0.0) {
            $span = 1.0;
        }

        $padX = 4.0;
        $padY = 8.0;
        $n = count($values);
        $pts = [];

        foreach ($values as $i => $v) {
            $x = $padX + ($i / ($n - 1)) * ($width - $padX * 2);
            $y = ($height - $padY) - (((float) $v - $min) / $span) * ($height - $padY * 2);
            $pts[] = [round($x, 1), round($y, 1)];
        }

        $line = [];

        foreach ($pts as $p) {
            $line[] = $p[0] . ',' . $p[1];
        }

        $first = $pts[0];
        $last = $pts[count($pts) - 1];
        $area = $line;
        $area[] = $last[0] . ',' . ($height - 2);
        $area[] = $first[0] . ',' . ($height - 2);

        return [
            'points' => implode(' ', $line),
            'area' => implode(' ', $area),
            'min_label' => number_format((float) $min),
            'max_label' => number_format((float) $max),
        ];
    }

    /**
     * @param list<int|float> $values
     */
    private function sparklinePoints(array $values, int $width = 220, int $height = 48): string
    {
        return $this->dayChart($values, $width, $height)['points'];
    }

    private function formatPct(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $n = (float) $value;
        $sign = $n > 0 ? '+' : '';

        return $sign . number_format($n, 1) . '%';
    }

    private function optionalPct(mixed $raw): ?int
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $n = (int) $raw;

        return max(1, min(200, $n));
    }

    private function optionalInt(mixed $raw): ?int
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        return max(1, min(100, (int) $raw));
    }
}
