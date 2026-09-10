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
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminAccountsController extends AdminController
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
        private AccountRepository $accounts,
        private PlayerRepository $players,
        private LogRepository $logs,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->accounts->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->accounts->countForGrid($q),
            fn ($q) => $this->withPlayerIndexEmpires($this->accounts->listForGrid($q)),
        );

        return $this->adminView('accounts', 'pages/accounts.twig', [
            'title' => $this->t('admin.accounts.title'),
            'pageLead' => $this->t('admin.accounts.lead'),
            'headerHref' => '/admin/game/accounts/new',
            'headerActionLabel' => $this->t('admin.accounts.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->accounts->gridDefinition()->spec(),
            '/admin/game/accounts',
            [
                'block' => fn (int $id): bool => $this->accounts->block($id),
                'unblock' => fn (int $id): bool => $this->accounts->unblock($id),
                'delete' => fn (int $id): bool => $this->accounts->delete($id),
            ],
            'account',
            'admin.accounts.mass_done',
            'game/accounts/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView();
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminResource('game/accounts/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/accounts/new');
        }

        $input = $this->formInput();

        try {
            $account = $this->accounts->create(
                $input['login'],
                $input['email'],
                (string) ($_POST['password'] ?? ''),
                trim((string) ($_POST['social_id'] ?? '')),
            );

            $this->accounts->updateAdmin(
                (int) $account['id'],
                $input['login'],
                $input['email'],
                $input['status'],
                $input['cash'],
                $input['mileage'],
            );

            $this->audit('account.create', 'account', (int) $account['id']);
            $this->flash('success', $this->t('admin.accounts.created'));

            return $this->redirect('/admin/game/accounts');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $account = $this->accounts->findForAdmin((int) $id);

        if ($account === null) {
            $this->flash('error', $this->t('admin.accounts.not_found'));

            return $this->redirect('/admin/game/accounts');
        }

        return $this->formView($account);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game/accounts/edit')) {
            return $redirect;
        }

        $accountId = (int) $id;
        $account = $this->accounts->findForAdmin($accountId);

        if ($account === null) {
            $this->flash('error', $this->t('admin.accounts.not_found'));

            return $this->redirect('/admin/game/accounts');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/accounts/' . $accountId);
        }

        $input = $this->formInput();

        try {
            $this->accounts->updateAdmin(
                $accountId,
                $input['login'],
                $input['email'],
                $input['status'],
                $input['cash'],
                $input['mileage'],
                (string) ($_POST['password'] ?? ''),
                trim((string) ($_POST['social_id'] ?? '')),
            );
            $this->audit('account.update', 'account', $accountId);
            $this->flash('success', $this->t('admin.accounts.updated'));

            return $this->redirect('/admin/game/accounts/' . $accountId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(
                array_merge($account, $input),
                $this->t($e->getMessage()),
                422,
            );
        }
    }

    public function block(string $id): Response
    {
        return $this->mutateStatus((int) $id, 'block', 'admin.accounts.blocked');
    }

    public function unblock(string $id): Response
    {
        return $this->mutateStatus((int) $id, 'unblock', 'admin.accounts.unblocked');
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game/accounts/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/accounts');
        }

        try {
            if (!$this->accounts->delete((int) $id)) {
                $this->flash('error', $this->t('admin.accounts.not_found'));
            } else {
                $this->audit('account.delete', 'account', (int) $id);
                $this->flash('success', $this->t('admin.accounts.deleted'));
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t('admin.accounts.not_found'));
        }

        return $this->redirect('/admin/game/accounts');
    }

    /**
     * @param array<string, mixed> $account
     */
    private function formView(array $account = [], ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $isEdit = isset($account['id']) && (int) $account['id'] > 0;
        $tab = $isEdit ? $this->requestedTab(['dados', 'activity'], 'dados') : 'dados';
        $characters = [];
        $connectionIps = [];

        if ($isEdit && $tab === 'activity') {
            $characters = $this->players->findByAccountId((int) $account['id']);
            $connectionIps = $this->logs->ipsForAccount((int) $account['id']);
        }

        $data = [
            'title' => $this->t($isEdit ? 'admin.accounts.edit_title' : 'admin.accounts.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.accounts.edit_lead' : 'admin.accounts.create_lead'),
            'formId' => 'admin-account-form',
            'saveLabel' => $this->t('admin.save'),
            'account' => $isEdit ? $this->withPlayerIndexEmpire($account) : $account,
            'characters' => $characters,
            'connectionIps' => $connectionIps,
            'isEdit' => $isEdit,
            'activeTab' => $tab,
            'error' => $error,
        ];

        if ($isEdit && $this->wantsTabPartial()) {
            if ($tab !== 'activity') {
                return Response::notFound();
            }

            return $this->adminFragment('components/account-activity.twig', $data);
        }

        return $this->adminView('accounts', 'pages/account-form.twig', $data, $status);
    }

    /**
     * @return array{login: string, email: string, status: string, cash: int, mileage: int}
     */
    private function formInput(): array
    {
        return [
            'login' => trim((string) ($_POST['login'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'OK'),
            'cash' => max(0, (int) ($_POST['cash'] ?? 0)),
            'mileage' => max(0, (int) ($_POST['mileage'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function withPlayerIndexEmpire(array $account): array
    {
        $account['empire'] = $this->players->findEmpireByAccountId((int) ($account['id'] ?? 0));

        return $account;
    }

    /**
     * @param list<array<string, mixed>> $accounts
     * @return list<array<string, mixed>>
     */
    private function withPlayerIndexEmpires(array $accounts): array
    {
        $ids = [];

        foreach ($accounts as $account) {
            $ids[] = (int) ($account['id'] ?? 0);
        }

        $map = $this->players->mapEmpiresByAccountIds($ids);

        foreach ($accounts as $index => $account) {
            $id = (int) ($account['id'] ?? 0);
            $accounts[$index]['empire'] = $map[$id] ?? 0;
        }

        return $accounts;
    }

    private function mutateStatus(int $id, string $action, string $successKey): Response
    {
        if ($redirect = $this->requireAdminResource('game/accounts/block')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/accounts');
        }

        try {
            if ($action === 'block') {
                $this->accounts->block($id);
            } else {
                $this->accounts->unblock($id);
            }

            $this->audit('account.' . $action, 'account', $id);
            $this->flash('success', $this->t($successKey));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/game/accounts');
    }
}
