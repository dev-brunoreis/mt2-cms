<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\DashboardPlayersGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminDashboardController extends AdminController
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
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $user = $this->adminAuth->user();
        $canStats = $this->acl->isAllowed($user, 'overview/dashboard/stats/view');
        $canPlayers = $this->acl->isAllowed($user, 'overview/dashboard/players/view');

        $data = [
            'title' => $this->t('admin.dashboard.title'),
            'pageLead' => $this->t('admin.dashboard.lead'),
            'canStats' => $canStats,
            'canPlayers' => $canPlayers,
        ];

        if ($canStats) {
            $spec = DashboardPlayersGrid::definition()->spec();
            $query = $this->gridQuery($spec);
            $range = $query->filter('range', '5m');
            $minutes = PlayerRepository::rangeMinutes($range);
            $data['playerCount'] = $this->players->countActiveSinceMinutes($minutes);
            $data['accountCount'] = $this->players->countAccountsActiveSinceMinutes($minutes);
        }

        if ($canPlayers) {
            $spec = DashboardPlayersGrid::definition()->spec();
            $query = $this->gridQuery($spec);
            $data['grid'] = GridRunner::fetch(
                $spec,
                $query,
                fn ($q) => $this->players->countActiveForGrid($q),
                fn ($q) => $this->players->listActiveForGrid($q),
            );
        }

        return $this->adminView('dashboard', 'pages/dashboard.twig', $data);
    }
}
