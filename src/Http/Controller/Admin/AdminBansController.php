<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Repository\BanRepository;
use Mt2Cms\Service\BanService;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminBansController extends AdminController
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
        private BanRepository $bans,
        private BanService $banService,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->bans->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->bans->countForGrid($q),
            fn ($q) => $this->bans->listForGrid($q),
        );

        return $this->adminView('bans', 'pages/bans.twig', [
            'title' => $this->t('admin.bans.title'),
            'pageLead' => $this->t('admin.bans.lead'),
            'headerHref' => '/admin/game/bans/new',
            'headerActionLabel' => $this->t('admin.bans.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->bans->gridDefinition()->spec(),
            '/admin/game/bans',
            [
                'lift' => function (int $id): bool {
                    $ban = $this->bans->findById($id);

                    if ($ban === null || $ban['lifted_at'] !== null) {
                        return false;
                    }

                    $this->banService->lift($id, (int) $ban['account_id']);
                    $this->audit('ban.lift', 'ban', $id);

                    return true;
                },
            ],
            'ban',
            'admin.bans.mass_done',
            'game/bans/mass',
        );
    }

    public function create(): Response
    {
        return $this->adminView('bans', 'pages/ban-form.twig', [
            'title' => $this->t('admin.bans.create'),
            'pageLead' => $this->t('admin.bans.create_lead'),
            'formId' => 'admin-ban-form',
            'saveLabel' => $this->t('admin.save'),
            'error' => null,
            'values' => ['login' => '', 'reason' => '', 'expires_at' => ''],
        ]);
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminResource('game/bans/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/bans/new');
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $expiresRaw = trim((string) ($_POST['expires_at'] ?? ''));
        $expiresAt = $expiresRaw !== '' ? $expiresRaw : null;
        $account = $this->accounts->findByLogin($login);

        if ($account === null) {
            return $this->adminView('bans', 'pages/ban-form.twig', [
                'title' => $this->t('admin.bans.create'),
                'pageLead' => $this->t('admin.bans.create_lead'),
                'formId' => 'admin-ban-form',
                'saveLabel' => $this->t('admin.save'),
                'error' => $this->t('admin.bans.account_not_found'),
                'values' => ['login' => $login, 'reason' => $reason, 'expires_at' => $expiresRaw],
            ], 422);
        }

        $admin = $this->adminAuth->user();
        $adminId = is_array($admin) ? (int) ($admin['id'] ?? 0) : null;
        $this->banService->apply((int) $account['id'], $login, $reason, $expiresAt, $adminId ?: null);
        $this->audit('ban.create', 'account', (int) $account['id'], ['login' => $login]);
        $this->flash('success', $this->t('admin.bans.created'));

        return $this->redirect('/admin/game/bans');
    }
}
