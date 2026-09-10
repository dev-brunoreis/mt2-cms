<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminSectionCatalog;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\AdminRoleRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminAdminsController extends AdminController
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
        private AdminRepository $admins,
        private AdminRoleRepository $roles,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->admins->gridDefinition()
            ->filterOptions('role', $this->admins->roleFilterOptions(), false)
            ->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->admins->countForGrid($q),
            fn ($q) => $this->admins->listForGrid($q),
        );

        return $this->adminView('admins', 'pages/admins.twig', [
            'title' => $this->t('admin.admins.title'),
            'pageLead' => $this->t('admin.admins.lead'),
            'headerHref' => '/admin/system/admins/new',
            'headerActionLabel' => $this->t('admin.admins.create'),
            'grid' => $grid,
        ]);
    }

    public function create(): Response
    {
        return $this->formView();
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminSection('admins')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/admins/new');
        }

        $input = $this->formInput();

        try {
            $id = $this->admins->create(
                $input['login'],
                (string) ($_POST['password'] ?? ''),
                $input['role'],
            );

            if ($input['use_custom_acl']) {
                $this->admins->update($id, $input['login'], $input['role'], true);
                $this->acl->saveAdminSections($id, $input['sections']);
            }

            $this->audit('admin.create', 'admin', $id, [
                'role' => $input['role'],
                'use_custom_acl' => $input['use_custom_acl'],
                'sections' => $input['use_custom_acl'] ? $input['sections'] : null,
            ]);
            $this->flash('success', $this->t('admin.admins.created'));

            return $this->redirect('/admin/system/admins');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $admin = $this->admins->findById((int) $id);

        if ($admin === null) {
            return $this->adminView('admins', 'pages/placeholder.twig', [
                'title' => $this->t('admin.admins.not_found'),
            ], 404);
        }

        return $this->formView([
            'admin' => $admin,
            'sections' => $this->acl->adminSections((int) $admin['id']),
        ]);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('admins')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/admins/' . $id);
        }

        $input = $this->formInput();
        $adminId = (int) $id;
        $password = trim((string) ($_POST['password'] ?? ''));

        try {
            $this->admins->update(
                $adminId,
                $input['login'],
                $input['role'],
                $input['use_custom_acl'],
                $password !== '' ? $password : null,
            );

            if ($input['use_custom_acl']) {
                $this->acl->saveAdminSections($adminId, $input['sections']);
            } else {
                $this->acl->saveAdminSections($adminId, []);
            }

            $this->audit('admin.update', 'admin', $adminId, [
                'role' => $input['role'],
                'use_custom_acl' => $input['use_custom_acl'],
                'sections' => $input['use_custom_acl'] ? $input['sections'] : null,
            ]);
            $this->flash('success', $this->t('admin.admins.updated'));

            return $this->redirect('/admin/system/admins');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $admin = $this->admins->findById($adminId);

            return $this->formView([
                'admin' => $admin ?? ['id' => $adminId],
                'sections' => $input['sections'],
            ] + $input, $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('admins')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/admins');
        }

        $adminId = (int) $id;

        if ($adminId === (int) ($this->adminAuth->id() ?? 0)) {
            $this->flash('error', $this->t('admin.admins.cannot_delete_self'));

            return $this->redirect('/admin/system/admins');
        }

        try {
            if ($this->admins->delete($adminId)) {
                $this->audit('admin.delete', 'admin', $adminId);
                $this->flash('success', $this->t('admin.admins.deleted'));
            }
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/system/admins');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formView(array $data = [], ?string $error = null, int $status = 200): Response
    {
        $admin = $data['admin'] ?? null;
        $isEdit = is_array($admin) && isset($admin['id']);
        $roleOptions = $this->roles->listForSelect();
        $defaultRole = $roleOptions[0]['slug'] ?? 'support';

        return $this->adminView('admins', 'pages/admin-form.twig', [
            'title' => $this->t($isEdit ? 'admin.admins.edit_title' : 'admin.admins.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.admins.edit_lead' : 'admin.admins.create_lead'),
            'formId' => 'admin-admin-form',
            'isEdit' => $isEdit,
            'admin' => $admin ?? [
                'login' => $data['login'] ?? '',
                'role' => $data['role'] ?? $defaultRole,
                'use_custom_acl' => $data['use_custom_acl'] ?? false,
            ],
            'selectedSections' => $data['sections'] ?? [],
            'sectionGroups' => AdminSectionCatalog::grouped(),
            'assignableSections' => $this->acl->assignableSections(),
            'roleOptions' => $roleOptions,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array{login: string, role: string, use_custom_acl: bool, sections: list<string>}
     */
    private function formInput(): array
    {
        $role = strtolower(trim((string) ($_POST['role'] ?? '')));

        if (AdminPermissions::isSuper($role)) {
            throw new \InvalidArgumentException('admin.admins.cannot_assign_super');
        }

        $sections = [];

        foreach ((array) ($_POST['sections'] ?? []) as $sectionId) {
            $sectionId = (string) $sectionId;

            if ($sectionId !== '') {
                $sections[] = $sectionId;
            }
        }

        return [
            'login' => trim((string) ($_POST['login'] ?? '')),
            'role' => $role,
            'use_custom_acl' => (string) ($_POST['use_custom_acl'] ?? '') === '1',
            'sections' => $sections,
        ];
    }
}
