<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\DownloadRepository;
use Mt2Cms\Service\DownloadUploadService;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminDownloadsController extends AdminController
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
        private DownloadRepository $downloads,
        private DownloadUploadService $uploads,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->downloads->gridDefinition()->spec();
        $grid = GridRunner::fetch(
            $spec,
            $this->gridQuery($spec),
            fn ($q) => $this->downloads->countForGrid($q),
            fn ($q) => $this->downloads->listForGrid($q),
        );

        return $this->adminView('downloads', 'pages/downloads.twig', [
            'title' => $this->t('admin.downloads.title'),
            'pageLead' => $this->t('admin.downloads.lead'),
            'headerHref' => '/admin/content/downloads/new',
            'headerActionLabel' => $this->t('admin.downloads.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->downloads->gridDefinition()->spec(),
            '/admin/content/downloads',
            [
                'delete' => function (int $id): bool {
                    $row = $this->downloads->findById($id);

                    if ($row === null) {
                        return false;
                    }

                    $stored = trim((string) ($row['stored_name'] ?? ''));

                    if ($stored !== '') {
                        $this->uploads->delete($stored);
                    }

                    return $this->downloads->delete($id);
                },
            ],
            'download',
            'admin.downloads.mass_done',
            'content/downloads/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView(null, ['title' => '', 'category' => 'client', 'description' => '', 'external_url' => '', 'sort_order' => 0, 'enabled' => true]);
    }

    public function edit(int $id): Response
    {
        $row = $this->downloads->findById($id);

        if ($row === null) {
            $this->flash('error', $this->t('admin.downloads.not_found'));

            return $this->redirect('/admin/content/downloads');
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
        $resource = $id === null ? 'content/downloads/create' : 'content/downloads/edit';

        if ($redirect = $this->requireAdminResource($resource)) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($id === null ? '/admin/content/downloads/new' : '/admin/content/downloads/' . $id);
        }

        $data = $this->readForm();

        try {
            if (isset($_FILES['file']) && is_array($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->uploads->store($_FILES['file']);
                $data['stored_name'] = $stored['stored_name'];
                $data['original_name'] = $stored['original_name'];
                $data['external_url'] = null;
            }

            if ($id === null) {
                $newId = $this->downloads->create($data);
                $this->audit('download.create', 'download', (string) $newId);
            } else {
                $existing = $this->downloads->findById($id);

                if ($existing === null) {
                    $this->flash('error', $this->t('admin.downloads.not_found'));

                    return $this->redirect('/admin/content/downloads');
                }

                if (!isset($data['stored_name'])) {
                    $data['stored_name'] = $existing['stored_name'];
                    $data['original_name'] = $existing['original_name'];
                } elseif ($existing['stored_name']) {
                    $this->uploads->delete((string) $existing['stored_name']);
                }

                $this->downloads->update($id, $data);
                $this->audit('download.update', 'download', (string) $id);
            }

            $this->flash('success', $this->t('admin.saved'));

            return $this->redirect('/admin/content/downloads');
        } catch (\InvalidArgumentException $e) {
            return $this->formView($id !== null ? array_merge($this->downloads->findById($id) ?? [], $data) : $data, $this->t($e->getMessage()), 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readForm(): array
    {
        $category = trim((string) ($_POST['category'] ?? 'client'));

        if (!in_array($category, ['client', 'patch', 'tools', 'other'], true)) {
            throw new \InvalidArgumentException('admin.downloads.invalid_category');
        }

        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category' => $category,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'external_url' => trim((string) ($_POST['external_url'] ?? '')),
            'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
            'enabled' => isset($_POST['enabled']),
        ];
    }

    /**
     * @param array<string, mixed>|null $values
     */
    private function formView(?array $values, ?string $error = null, int $status = 200): Response
    {
        return $this->adminView('downloads', 'pages/download-form.twig', [
            'title' => $values && isset($values['id']) ? $this->t('admin.downloads.edit') : $this->t('admin.downloads.create'),
            'pageLead' => $this->t('admin.downloads.form_lead'),
            'download' => $values,
            'error' => $error,
        ], $status);
    }
}
