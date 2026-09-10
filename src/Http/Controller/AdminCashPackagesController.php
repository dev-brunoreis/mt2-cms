<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminCashPackagesController extends AdminController
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
        private CashPackageRepository $packages,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->packages->gridDefinition()->spec();
        $grid = GridRunner::fetch(
            $spec,
            $this->gridQuery($spec),
            fn ($q) => $this->packages->countForGrid($q),
            fn ($q) => $this->packages->listForGrid($q),
        );

        return $this->adminView('packages', 'pages/packages.twig', [
            'title' => $this->t('admin.packages.title'),
            'pageLead' => $this->t('admin.packages.lead'),
            'headerHref' => '/admin/store/packages/new',
            'headerActionLabel' => $this->t('admin.packages.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->packages->gridDefinition()->spec(),
            '/admin/store/packages',
            ['delete' => fn (int $id): bool => $this->packages->delete($id)],
            'package',
            'admin.packages.mass_done',
            'store/packages/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView(null);
    }

    public function edit(int $id): Response
    {
        $row = $this->packages->findById($id);

        if ($row === null) {
            $this->flash('error', $this->t('admin.packages.not_found'));

            return $this->redirect('/admin/store/packages');
        }

        return $this->formView($row);
    }

    public function store(): Response
    {
        return $this->save(null);
    }

    public function update(int $id): Response
    {
        return $this->save($id);
    }

    private function save(?int $id): Response
    {
        $resource = $id === null ? 'store/packages/create' : 'store/packages/edit';

        if ($redirect = $this->requireAdminResource($resource)) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($id === null ? '/admin/store/packages/new' : '/admin/store/packages/' . $id);
        }

        $data = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'cash_amount' => max(1, (int) ($_POST['cash_amount'] ?? 0)),
            'price_cents' => max(1, (int) ($_POST['price_cents'] ?? 0)),
            'currency' => strtoupper(trim((string) ($_POST['currency'] ?? $this->settings->paypalCurrency()))),
            'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
            'enabled' => isset($_POST['enabled']),
        ];

        if ($data['title'] === '') {
            return $this->formView($data, $this->t('admin.packages.title_required'), 422);
        }

        if ($id === null) {
            $newId = $this->packages->create($data);
            $this->audit('package.create', 'package', (string) $newId);
        } else {
            $this->packages->update($id, $data);
            $this->audit('package.update', 'package', (string) $id);
        }

        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/store/packages');
    }

    /**
     * @param array<string, mixed>|null $values
     */
    private function formView(?array $values, ?string $error = null, int $status = 200): Response
    {
        return $this->adminView('packages', 'pages/package-form.twig', [
            'title' => $values && isset($values['id']) ? $this->t('admin.packages.edit') : $this->t('admin.packages.create'),
            'pageLead' => $this->t('admin.packages.form_lead'),
            'package' => $values,
            'error' => $error,
            'defaultCurrency' => $this->settings->paypalCurrency(),
        ], $status);
    }
}
