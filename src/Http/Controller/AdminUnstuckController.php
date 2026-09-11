<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;
use Mt2Cms\Unstuck\UnstuckService;

class AdminUnstuckController extends AdminController
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
        private SettingsService $settings,
        private UnstuckService $unstuck,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function settings(): Response
    {
        return $this->adminView('unstuck', 'pages/unstuck-settings.twig', [
            'title' => $this->t('admin.unstuck.title'),
            'pageLead' => $this->t('admin.unstuck.lead'),
            'unstuckEnabled' => $this->settings->unstuckEnabled(),
            'cooldownMinutes' => $this->settings->unstuckCooldownMinutes(),
            'spawns' => $this->settings->unstuckSpawns(),
            'positionColumnsAvailable' => $this->unstuck->hasPositionColumns(),
            'formId' => 'admin-unstuck-form',
            'saveLabel' => $this->t('admin.save'),
        ]);
    }

    public function saveSettings(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/unstuck/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/unstuck');
        }

        $before = [
            'unstuck_enabled' => $this->settings->unstuckEnabled(),
            'unstuck_cooldown_minutes' => $this->settings->unstuckCooldownMinutes(),
            'unstuck_spawns' => $this->settings->unstuckSpawns(),
        ];

        $this->settings->setUnstuckEnabled(isset($_POST['unstuck_enabled']));
        $this->settings->setUnstuckCooldownMinutes((int) ($_POST['unstuck_cooldown_minutes'] ?? 60));

        $spawns = [];

        foreach ([1, 2, 3] as $empire) {
            $spawns[$empire] = [
                'map_index' => (int) ($_POST['spawn_map_' . $empire] ?? 0),
                'x' => (int) ($_POST['spawn_x_' . $empire] ?? 0),
                'y' => (int) ($_POST['spawn_y_' . $empire] ?? 0),
            ];
        }

        $this->settings->setUnstuckSpawns($spawns);

        $after = [
            'unstuck_enabled' => $this->settings->unstuckEnabled(),
            'unstuck_cooldown_minutes' => $this->settings->unstuckCooldownMinutes(),
            'unstuck_spawns' => $this->settings->unstuckSpawns(),
        ];

        $this->auditChange('settings.unstuck_save', 'settings', null, $before, $after);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/settings/unstuck');
    }
}
