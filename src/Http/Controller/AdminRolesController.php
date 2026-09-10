<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminSectionCatalog;
use Mt2Cms\Admin\RoleSlugExistsException;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AdminRoleRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminRolesController extends AdminController
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
        private AdminRoleRepository $roles,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->roles->gridDefinition()->spec(),
            '/admin/system/roles',
            [
                'delete' => fn (string $slug): bool => $this->roles->delete($slug),
            ],
            'admin_role',
            'admin.roles.mass_done',
            'roles',
        );
    }

    public function index(): Response
    {
        $spec = $this->roles->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->roles->countForGrid($q),
            fn ($q) => $this->roles->listForGrid($q),
        );
        $grid = $this->roles->augmentGridWithSuper($grid, $query);

        return $this->adminView('roles', 'pages/roles.twig', [
            'title' => $this->t('admin.roles.title'),
            'pageLead' => $this->t('admin.roles.lead'),
            'headerHref' => '/admin/system/roles/new',
            'headerActionLabel' => $this->t('admin.roles.create'),
            'grid' => $grid,
            'superAdminCount' => $this->roles->countSuperAdmins(),
        ]);
    }

    public function create(): Response
    {
        return $this->formView();
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminSection('roles')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/roles/new');
        }

        $input = $this->formInput();

        try {
            $slug = $this->roles->create($input['label'], $input['slug'], $input['sections']);
            $this->audit('role.create', 'admin_role', null, [
                'slug' => $slug,
                'sections' => $input['sections'],
            ]);
            $this->flash('success', $this->t('admin.roles.created'));

            return $this->redirect('/admin/system/roles');
        } catch (RoleSlugExistsException $e) {
            $input['slug'] = '';

            return $this->formView($input, $this->t($e->getMessage(), ['slug' => $e->slug]), 422);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $slug): Response
    {
        if (AdminPermissions::isSuper($slug)) {
            return $this->systemRoleView();
        }

        $role = $this->roles->findBySlug($slug);

        if ($role === null) {
            return $this->adminView('roles', 'pages/placeholder.twig', [
                'title' => $this->t('admin.roles.not_found'),
            ], 404);
        }

        return $this->formView([
            'role' => $role,
            'sections' => $this->acl->roleSections($slug),
        ]);
    }

    public function update(string $slug): Response
    {
        if (AdminPermissions::isSuper($slug)) {
            $this->flash('error', $this->t('admin.roles.super_readonly'));

            return $this->redirect('/admin/system/roles/super');
        }

        if ($redirect = $this->requireAdminSection('roles')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/roles/' . rawurlencode($slug));
        }

        $input = $this->formInput();

        try {
            $this->roles->update($slug, $input['label'], $input['sections']);
            $this->audit('role.update', 'admin_role', null, [
                'slug' => $slug,
                'sections' => $input['sections'],
            ]);
            $this->flash('success', $this->t('admin.roles.updated'));

            return $this->redirect('/admin/system/roles');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView([
                'role' => ['slug' => $slug, 'label' => $input['label']],
                'sections' => $input['sections'],
            ], $this->t($e->getMessage()), 422);
        }
    }

    public function destroy(string $slug): Response
    {
        if (AdminPermissions::isSuper($slug)) {
            $this->flash('error', $this->t('admin.roles.super_readonly'));

            return $this->redirect('/admin/system/roles');
        }

        if ($redirect = $this->requireAdminSection('roles')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/system/roles');
        }

        try {
            if ($this->roles->delete($slug)) {
                $this->audit('role.delete', 'admin_role', null, ['slug' => $slug]);
                $this->flash('success', $this->t('admin.roles.deleted'));
            }
        } catch (\RuntimeException $e) {
            $key = $e->getMessage();
            $this->flash(
                'error',
                $key === 'admin.roles.in_use'
                    ? $this->t($key, ['count' => $this->roles->countAdminsBySlug($slug)])
                    : $this->t($key),
            );
        }

        return $this->redirect('/admin/system/roles');
    }

    private function systemRoleView(): Response
    {
        return $this->adminView('roles', 'pages/role-system.twig', [
            'title' => $this->t('admin.roles.super_title'),
            'pageLead' => $this->t('admin.roles.super_lead'),
            'adminCount' => $this->roles->countSuperAdmins(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formView(array $data = [], ?string $error = null, int $status = 200): Response
    {
        $role = $data['role'] ?? null;
        $isEdit = is_array($role) && isset($role['slug']);
        $slug = $isEdit ? (string) $role['slug'] : '';

        return $this->adminView('roles', 'pages/role-form.twig', [
            'title' => $this->t($isEdit ? 'admin.roles.edit_title' : 'admin.roles.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.roles.edit_lead' : 'admin.roles.create_lead'),
            'formId' => 'admin-role-form',
            'isEdit' => $isEdit,
            'role' => $role ?? [
                'label' => $data['label'] ?? '',
                'slug' => $data['slug'] ?? '',
            ],
            'selectedSections' => $data['sections'] ?? [],
            'sectionGroups' => AdminSectionCatalog::grouped(),
            'assignableSections' => $this->acl->assignableSections(),
            'adminCount' => $isEdit ? $this->roles->countAdminsBySlug($slug) : 0,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array{label: string, slug: string|null, sections: list<string>}
     */
    private function formInput(): array
    {
        $sections = [];

        foreach ((array) ($_POST['sections'] ?? []) as $sectionId) {
            $sectionId = (string) $sectionId;

            if ($sectionId !== '') {
                $sections[] = $sectionId;
            }
        }

        $slug = trim((string) ($_POST['slug'] ?? ''));

        return [
            'label' => trim((string) ($_POST['label'] ?? '')),
            'slug' => $slug !== '' ? $slug : null,
            'sections' => $sections,
        ];
    }
}
