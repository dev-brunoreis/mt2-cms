<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
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
        AdminAuditService $auditLog,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog);
    }

    public function index(): Response
    {
        $spec = $this->players->dashboardGridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $range = $query->filter('range', '5m');
        $minutes = PlayerRepository::rangeMinutes($range);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->players->countActiveForGrid($q),
            fn ($q) => $this->players->listActiveForGrid($q),
        );

        return $this->adminView('dashboard', 'pages/dashboard.twig', [
            'title' => $this->t('admin.dashboard.title'),
            'pageLead' => $this->t('admin.dashboard.lead'),
            'playerCount' => $this->players->countActiveSinceMinutes($minutes),
            'accountCount' => $this->players->countAccountsActiveSinceMinutes($minutes),
            'grid' => $grid,
        ]);
    }
}
