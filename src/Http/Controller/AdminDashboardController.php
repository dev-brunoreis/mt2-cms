<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class AdminDashboardController extends AdminController
{
    private const PER_PAGE = 20;

    /** @var array<string, int> */
    private const RANGES = [
        '5m' => 5,
        '1h' => 60,
        '12h' => 720,
        '24h' => 1440,
        '7d' => 10080,
    ];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $range = (string) ($_GET['range'] ?? '5m');

        if (!isset(self::RANGES[$range])) {
            $range = '5m';
        }

        $minutes = self::RANGES[$range];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->players->countActiveSinceMinutes($minutes);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('dashboard', 'pages/dashboard.twig', [
            'title' => $this->t('admin.dashboard.title'),
            'pageLead' => $this->t('admin.dashboard.lead'),
            'range' => $range,
            'ranges' => array_keys(self::RANGES),
            'playerCount' => $total,
            'accountCount' => $this->players->countAccountsActiveSinceMinutes($minutes),
            'characters' => $this->players->listActiveSinceMinutes($minutes, $page, self::PER_PAGE),
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
