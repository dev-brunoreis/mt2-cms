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
        private CommonRepository $common,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
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
            'headerHref' => '/admin/gms/new',
            'headerActionLabel' => $this->t('admin.gms.create'),
            'grid' => $grid,
            'hosts' => $this->common->gmHosts(),
        ]);
    }

    public function mass(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms');
        }

        $action = $this->gridMassAction();
        $ids = $this->gridMassIds();
        $count = 0;

        foreach ($ids as $id) {
            try {
                if ($action !== 'delete' || !$this->common->deleteGm($id)) {
                    throw new \RuntimeException('skip');
                }

                $count++;
            } catch (\RuntimeException) {
                continue;
            }
        }

        $this->flash('success', $this->t('admin.gms.mass_done', ['count' => $count]));

        return $this->redirect('/admin/gms');
    }

    public function create(): Response
    {
        return $this->formView();
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms/new');
        }

        $input = $this->formInput();

        try {
            $this->assertAccountExists((string) $input['mAccount']);
            $this->common->createGm($input);
            $this->flash('success', $this->t('admin.gms.created'));

            return $this->redirect('/admin/gms');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $gm = $this->common->findGmById((int) $id);

        if ($gm === null) {
            $this->flash('error', $this->t('admin.gms.not_found'));

            return $this->redirect('/admin/gms');
        }

        return $this->formView($gm);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $gmId = (int) $id;
        $gm = $this->common->findGmById($gmId);

        if ($gm === null) {
            $this->flash('error', $this->t('admin.gms.not_found'));

            return $this->redirect('/admin/gms');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms/' . $gmId);
        }

        $input = $this->formInput();

        try {
            $this->assertAccountExists((string) $input['mAccount']);
            $this->common->updateGm($gmId, $input);
            $this->flash('success', $this->t('admin.gms.updated'));

            return $this->redirect('/admin/gms/' . $gmId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($gm, $input), $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms');
        }

        if (!$this->common->deleteGm((int) $id)) {
            $this->flash('error', $this->t('admin.gms.not_found'));
        } else {
            $this->flash('success', $this->t('admin.gms.deleted'));
        }

        return $this->redirect('/admin/gms');
    }

    public function addHost(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms');
        }

        try {
            $this->common->addGmHost(trim((string) ($_POST['mIP'] ?? '')));
            $this->flash('success', $this->t('admin.gms.host_added'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/gms');
    }

    public function deleteHost(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/gms');
        }

        if (!$this->common->deleteGmHost(trim((string) ($_POST['mIP'] ?? '')))) {
            $this->flash('error', $this->t('admin.gms.host_not_found'));
        } else {
            $this->flash('success', $this->t('admin.gms.host_deleted'));
        }

        return $this->redirect('/admin/gms');
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
