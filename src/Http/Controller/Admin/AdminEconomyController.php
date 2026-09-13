<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\EconomyGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
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

        $lastOk = $this->economy->lastOkAt();
        $stale = $lastOk === null || strtotime($lastOk) < (time() - 7200);
        $yang = $this->economy->latestYang();
        $yang7 = $yang !== null
            ? $this->economy->yangForDay(date('Y-m-d', strtotime('-7 days')))
            : null;
        $alerts = $this->economy->listUnackedAlerts(20);
        $alertNames = $this->scan->itemNamesByVnum(array_column($alerts, 'vnum'));

        foreach ($alerts as $i => $alert) {
            $alerts[$i]['item_name'] = $alertNames[(int) $alert['vnum']] ?? ('#' . $alert['vnum']);
        }

        $totalYang = 0;

        if ($yang !== null) {
            $totalYang = (int) $yang['player_yang']
                + (int) $yang['safebox_yang']
                + (int) $yang['guild_yang'];
        }

        return $this->adminView('economy', 'pages/economy.twig', [
            'title' => $this->t('admin.economy.title'),
            'pageLead' => $this->t('admin.economy.lead'),
            'grid' => $grid,
            'tickStale' => $stale,
            'lastOkAt' => $lastOk,
            'yang' => $yang,
            'yang7' => $yang7,
            'totalYang' => $totalYang,
            'alerts' => $alerts,
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

        $unitsNow = (int) ($census['units'] ?? 0);
        $units7 = $this->economy->unitsOnDay($vnum, date('Y-m-d', strtotime('-7 days')));
        $supplyChange = null;

        if ($units7 !== null && $units7 > 0) {
            $supplyChange = ($unitsNow / $units7) - 1.0;
        }

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
            'trades' => $this->economy->recentTrades($vnum, 25),
            'medianNow' => $medianNow,
            'watched' => $watched,
            'watchCfg' => $watchCfg,
            'alerts' => $alerts,
            'drops' => $drops,
            'adviceKey' => $adviceKey,
            'supplyChange' => $supplyChange,
            'priceChange' => $priceChange,
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
