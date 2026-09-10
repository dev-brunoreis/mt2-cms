<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\CommonRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminGmsController extends AdminController
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
        private CommonRepository $common,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->common->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->common->countForGrid($q),
            fn ($q) => $this->common->listForGrid($q),
        );

        return $this->adminView('gms', 'pages/gms.twig', [
            'title' => $this->t('admin.gms.title'),
            'pageLead' => $this->t('admin.gms.lead'),
            'headerHref' => '/admin/game-data/gms/new',
            'headerActionLabel' => $this->t('admin.gms.create'),
            'grid' => $grid,
            'hosts' => $this->common->gmHosts(),
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->common->gridDefinition()->spec(),
            '/admin/game-data/gms',
            [
                'delete' => fn (int $id): bool => $this->common->deleteGm($id),
            ],
            'gm',
            'admin.gms.mass_done',
            'game-data/gms/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView();
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminResource('game-data/gms/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/gms/new');
        }

        $input = $this->formInput();

        try {
            $this->assertAccountExists((string) $input['mAccount']);
            $gm = $this->common->createGm($input);
            $this->audit('gm.create', 'gm', (int) $gm['mID']);
            $this->flash('success', $this->t('admin.gms.created'));

            return $this->redirect('/admin/game-data/gms');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $gm = $this->common->findGmById((int) $id);

        if ($gm === null) {
            $this->flash('error', $this->t('admin.gms.not_found'));

            return $this->redirect('/admin/game-data/gms');
        }

        return $this->formView($gm);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game-data/gms/edit')) {
            return $redirect;
        }

        $gmId = (int) $id;
        $gm = $this->common->findGmById($gmId);

        if ($gm === null) {
            $this->flash('error', $this->t('admin.gms.not_found'));

            return $this->redirect('/admin/game-data/gms');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/gms/' . $gmId);
        }

        $input = $this->formInput();

        try {
            $this->assertAccountExists((string) $input['mAccount']);
            $this->common->updateGm($gmId, $input);
            $this->auditChange('gm.update', 'gm', $gmId, [
                'mAccount' => $gm['mAccount'],
                'mName' => $gm['mName'],
                'mContactIP' => $gm['mContactIP'],
                'mServerIP' => $gm['mServerIP'],
                'mAuthority' => $gm['mAuthority'],
            ], $input);
            $this->flash('success', $this->t('admin.gms.updated'));

            return $this->redirect('/admin/game-data/gms/' . $gmId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($gm, $input), $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game-data/gms/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/gms');
        }

        if (!$this->common->deleteGm((int) $id)) {
            $this->flash('error', $this->t('admin.gms.not_found'));
        } else {
            $this->audit('gm.delete', 'gm', (int) $id);
            $this->flash('success', $this->t('admin.gms.deleted'));
        }

        return $this->redirect('/admin/game-data/gms');
    }

    public function addHost(): Response
    {
        if ($redirect = $this->requireAdminResource('game-data/gms/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/gms');
        }

        try {
            $this->common->addGmHost(trim((string) ($_POST['mIP'] ?? '')));
            $this->audit('gm.host_add', 'gm_host', null);
            $this->flash('success', $this->t('admin.gms.host_added'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/game-data/gms');
    }

    public function deleteHost(): Response
    {
        if ($redirect = $this->requireAdminResource('game-data/gms/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/gms');
        }

        if (!$this->common->deleteGmHost(trim((string) ($_POST['mIP'] ?? '')))) {
            $this->flash('error', $this->t('admin.gms.host_not_found'));
        } else {
            $this->audit('gm.host_delete', 'gm_host', null);
            $this->flash('success', $this->t('admin.gms.host_deleted'));
        }

        return $this->redirect('/admin/game-data/gms');
    }

    /**
     * @param array<string, mixed> $gm
     */
    private function formView(array $gm = [], ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $isEdit = isset($gm['mID']) && (int) $gm['mID'] > 0;

        return $this->adminView('gms', 'pages/gm-form.twig', [
            'title' => $this->t($isEdit ? 'admin.gms.edit_title' : 'admin.gms.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.gms.edit_lead' : 'admin.gms.create_lead'),
            'formId' => 'admin-gm-form',
            'saveLabel' => $this->t('admin.save'),
            'gm' => $gm,
            'isEdit' => $isEdit,
            'authorities' => CommonRepository::authorities(),
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(): array
    {
        return [
            'mAccount' => trim((string) ($_POST['mAccount'] ?? '')),
            'mName' => trim((string) ($_POST['mName'] ?? '')),
            'mContactIP' => trim((string) ($_POST['mContactIP'] ?? '')),
            'mServerIP' => trim((string) ($_POST['mServerIP'] ?? 'ALL')),
            'mAuthority' => trim((string) ($_POST['mAuthority'] ?? 'PLAYER')),
        ];
    }

    private function assertAccountExists(string $login): void
    {
        if ($this->accounts->findByLogin($login) === null) {
            throw new \InvalidArgumentException('admin.gms.account_not_found');
        }
    }
}
