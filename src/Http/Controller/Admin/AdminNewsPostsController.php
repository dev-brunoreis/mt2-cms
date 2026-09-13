<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\NewsGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Service\DiscordWebhookService;
use Mt2Cms\Http\Response;
use Mt2Cms\Service\SeoImageUploadService;

class AdminNewsPostsController extends AdminNewsBaseController
{
    public function __construct(
        \Mt2Cms\Theme\ThemeEngine $theme,
        \Mt2Cms\Auth\Auth $auth,
        \Mt2Cms\Auth\Csrf $csrf,
        \Mt2Cms\I18n\Translator $translator,
        \Mt2Cms\Auth\AdminAuth $adminAuth,
        \Mt2Cms\Theme\ThemeEngine $adminTheme,
        \Mt2Cms\Service\AclService $acl,
        \Mt2Cms\Service\AdminAuditService $auditLog,
        \Mt2Cms\Repository\NewsRepository $news,
        \Mt2Cms\Repository\NewsCommentRepository $comments,
        \Mt2Cms\Service\SettingsService $settings,
        \Mt2Cms\Support\HtmlSanitizer $sanitizer,
        \Mt2Cms\Service\NewsUploadService $uploads,
        private DiscordWebhookService $discord,
    ) {
        parent::__construct(
            $theme,
            $auth,
            $csrf,
            $translator,
            $adminAuth,
            $adminTheme,
            $acl,
            $auditLog,
            $news,
            $comments,
            $settings,
            $sanitizer,
            $uploads,
        );
    }

    public function index(): Response
    {
        $spec = NewsGrid::definition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->news->countForGrid($q),
            fn ($q) => $this->news->listForGrid($q),
        );

        return $this->adminView('news', 'pages/news.twig', [
            'title' => $this->t('admin.news.title'),
            'pageLead' => $this->t('admin.news.lead'),
            'headerHref' => '/admin/content/news/posts/new',
            'headerActionLabel' => $this->t('admin.news.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            NewsGrid::definition()->spec(),
            '/admin/content/news?tab=posts',
            [
                'publish' => fn (int $id): bool => $this->setNewsStatus($id, 'published'),
                'draft' => fn (int $id): bool => $this->setNewsStatus($id, 'draft'),
                'delete' => fn (int $id): bool => $this->news->delete($id),
            ],
            'news',
            'admin.news.mass_done',
            'content/news/posts/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView($this->prefill());
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/posts/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news/posts/new');
        }

        $input = $this->formInput();

        try {
            $this->validateInput($input);
            $admin = $this->adminAuth->user();

            if ($admin === null) {
                throw new \RuntimeException('admin.login_required');
            }

            $newsId = $this->news->create([
                'title' => $input['title'],
                'body' => $this->sanitizer->sanitize($input['body']),
                'cover_image' => $input['cover_image'],
                'author_admin_id' => (int) $admin['id'],
                'author_login' => (string) $admin['login'],
                'status' => $input['status'],
                'comments_enabled' => $input['comments_enabled'],
                'seo_title' => $input['seo_title'],
                'seo_description' => $input['seo_description'],
                'seo_og_image' => $input['seo_og_image'],
            ]);
            $this->audit('news.create', 'news', $newsId);
            $this->notifyIfPublished($newsId, $input['title'], $input['status'], null);
            $this->flash('success', $this->t('admin.news.created'));

            return $this->redirect('/admin/content/news?tab=posts');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422, $this->newsFormTabFromError($e));
        }
    }

    public function edit(string $id): Response
    {
        $post = $this->news->findById((int) $id);

        if ($post === null) {
            $this->flash('error', $this->t('admin.news.not_found'));

            return $this->redirect('/admin/content/news?tab=posts');
        }

        return $this->formView([
            'id' => (int) $post['id'],
            'title' => (string) $post['title'],
            'body' => (string) $post['body'],
            'cover_image' => $post['cover_image'] !== null ? (string) $post['cover_image'] : '',
            'seo_title' => $post['seo_title'] !== null ? (string) $post['seo_title'] : '',
            'seo_description' => $post['seo_description'] !== null ? (string) $post['seo_description'] : '',
            'seo_og_image' => $post['seo_og_image'] !== null ? (string) $post['seo_og_image'] : '',
            'status' => (string) $post['status'],
            'comments_enabled' => (int) $post['comments_enabled'] === 1,
            'author_login' => (string) $post['author_login'],
            'views' => (int) $post['views'],
            'published_at' => $post['published_at'],
        ], activeTab: $this->requestedNewsFormTab());
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/posts/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news/posts/' . (int) $id);
        }

        $existing = $this->news->findById((int) $id);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.news.not_found'));

            return $this->redirect('/admin/content/news?tab=posts');
        }

        $input = $this->formInput();
        $input['id'] = (int) $id;
        $input['author_login'] = (string) $existing['author_login'];
        $input['views'] = (int) $existing['views'];
        $input['published_at'] = $existing['published_at'];

        try {
            $this->validateInput($input);
            $body = $this->sanitizer->sanitize($input['body']);
            $this->news->update((int) $id, [
                'title' => $input['title'],
                'body' => $body,
                'cover_image' => $input['cover_image'],
                'status' => $input['status'],
                'comments_enabled' => $input['comments_enabled'],
                'seo_title' => $input['seo_title'],
                'seo_description' => $input['seo_description'],
                'seo_og_image' => $input['seo_og_image'],
            ]);
            $this->auditChange('news.update', 'news', (int) $id, [
                'title' => $existing['title'],
                'body' => $existing['body'],
                'cover_image' => $existing['cover_image'],
                'status' => $existing['status'],
                'comments_enabled' => $existing['comments_enabled'],
            ], [
                'title' => $input['title'],
                'body' => $body,
                'cover_image' => $input['cover_image'],
                'status' => $input['status'],
                'comments_enabled' => $input['comments_enabled'],
            ]);
            $this->notifyIfPublished((int) $id, $input['title'], $input['status'], (string) $existing['status']);
            $this->flash('success', $this->t('admin.news.update_ok'));

            return $this->redirect('/admin/content/news/posts/' . (int) $id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422, $this->newsFormTabFromError($e));
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/posts/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/news?tab=posts');
        }

        if (!$this->news->delete((int) $id)) {
            $this->flash('error', $this->t('admin.news.delete_failed'));
        } else {
            $this->audit('news.delete', 'news', (int) $id);
            $this->flash('success', $this->t('admin.news.deleted'));
        }

        return $this->redirect('/admin/content/news?tab=posts');
    }

    public function upload(): Response
    {
        if ($this->requireAnyAdminResource(['content/news/posts/create', 'content/news/posts/edit']) !== null) {
            if (!$this->adminAuth->check()) {
                return Response::json(['error' => $this->t('admin.login_required')], 401);
            }

            return Response::json(['error' => $this->t('admin.access_denied')], 403);
        }

        if (!$this->assertCsrf()) {
            return Response::json(['error' => $this->t('auth.invalid_csrf')], 403);
        }

        try {
            $file = $_FILES['file'] ?? null;

            if (!is_array($file)) {
                throw new \InvalidArgumentException('admin.news.upload_failed');
            }

            $url = $this->uploads->store($file);
            $this->audit('news.upload', 'news', null);

            return Response::json(['location' => $url]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return Response::json(['error' => $this->t($e->getMessage())], 422);
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function formView(array $post = [], ?string $error = null, int $status = 200, ?string $activeTab = null): Response
    {
        $isEdit = isset($post['id']);

        return $this->adminView('news', 'pages/news-form.twig', [
            'title' => $isEdit ? $this->t('admin.news.edit') : $this->t('admin.news.create_title'),
            'pageLead' => $this->t('admin.news.form_lead'),
            'formId' => 'admin-news-form',
            'saveLabel' => $this->t('admin.save'),
            'post' => $post,
            'isEdit' => $isEdit,
            'error' => $error,
            'activeTab' => $this->normalizeNewsFormTab($activeTab ?? $this->requestedNewsFormTab()),
        ], $status);
    }

    private function requestedNewsFormTab(): string
    {
        return $this->normalizeNewsFormTab((string) ($_GET['tab'] ?? 'data'));
    }

    private function newsFormTabFromError(\Throwable $error): string
    {
        return str_contains($error->getMessage(), 'invalid_seo') ? 'seo' : 'data';
    }

    private function normalizeNewsFormTab(string $tab): string
    {
        return in_array($tab, ['data', 'seo'], true) ? $tab : 'data';
    }

    /**
     * @return array{
     *   title: string,
     *   body: string,
     *   cover_image: string,
     *   status: string,
     *   comments_enabled: bool,
     *   seo_title: string,
     *   seo_description: string,
     *   seo_og_image: string
     * }
     */
    private function prefill(): array
    {
        return [
            'title' => '',
            'body' => '',
            'cover_image' => '',
            'status' => 'draft',
            'comments_enabled' => true,
            'seo_title' => '',
            'seo_description' => '',
            'seo_og_image' => '',
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   body: string,
     *   cover_image: ?string,
     *   status: string,
     *   comments_enabled: bool,
     *   seo_title: ?string,
     *   seo_description: ?string,
     *   seo_og_image: ?string
     * }
     */
    private function formInput(): array
    {
        $cover = trim((string) ($_POST['cover_image'] ?? ''));
        $seoImage = isset($_POST['remove_seo_og_image'])
            ? ''
            : trim((string) ($_POST['seo_og_image'] ?? ''));
        $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
        $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'draft');

        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'body' => (string) ($_POST['body'] ?? ''),
            'cover_image' => $cover !== '' ? $cover : null,
            'status' => $status,
            'comments_enabled' => isset($_POST['comments_enabled']),
            'seo_title' => $seoTitle !== '' ? $seoTitle : null,
            'seo_description' => $seoDescription !== '' ? $seoDescription : null,
            'seo_og_image' => $seoImage !== '' ? $seoImage : null,
        ];
    }

    /**
     * @param array{
     *   title: string,
     *   body: string,
     *   cover_image: ?string,
     *   status: string,
     *   comments_enabled: bool,
     *   seo_title?: ?string,
     *   seo_description?: ?string,
     *   seo_og_image?: ?string
     * } $input
     */
    private function validateInput(array $input): void
    {
        if ($input['title'] === '' || mb_strlen($input['title']) > 200) {
            throw new \InvalidArgumentException('admin.news.invalid_title');
        }

        $body = trim(strip_tags($input['body']));

        if ($body === '') {
            throw new \InvalidArgumentException('admin.news.invalid_body');
        }

        if ($input['cover_image'] !== null && !$this->isAllowedCover($input['cover_image'])) {
            throw new \InvalidArgumentException('admin.news.invalid_cover');
        }

        $seoTitle = (string) ($input['seo_title'] ?? '');

        if ($seoTitle !== '' && mb_strlen($seoTitle) > 70) {
            throw new \InvalidArgumentException('admin.news.invalid_seo_title');
        }

        $seoDescription = (string) ($input['seo_description'] ?? '');

        if ($seoDescription !== '' && mb_strlen($seoDescription) > 320) {
            throw new \InvalidArgumentException('admin.news.invalid_seo_description');
        }

        $seoImage = $input['seo_og_image'] ?? null;

        if ($seoImage !== null && $seoImage !== '' && !$this->isAllowedSeoImage((string) $seoImage)) {
            throw new \InvalidArgumentException('admin.news.invalid_seo_image');
        }
    }

    private function isAllowedCover(string $src): bool
    {
        if (!str_starts_with($src, '/uploads/news/')) {
            return false;
        }

        if (str_contains($src, '..') || str_contains($src, '\\')) {
            return false;
        }

        return (bool) preg_match('#^/uploads/news/[0-9]{4}/[0-9]{2}/[a-zA-Z0-9._-]+$#', $src);
    }

    private function isAllowedSeoImage(string $src): bool
    {
        if (SeoImageUploadService::isStoredPath($src)) {
            return true;
        }

        return $this->isAllowedCover($src);
    }

    private function setNewsStatus(int $id, string $status): bool
    {
        $post = $this->news->findById($id);

        if ($post === null) {
            return false;
        }

        $updated = $this->news->update($id, [
            'title' => (string) $post['title'],
            'body' => (string) $post['body'],
            'cover_image' => $post['cover_image'],
            'status' => $status,
            'comments_enabled' => (int) $post['comments_enabled'] === 1,
        ]);

        if ($updated) {
            $this->notifyIfPublished($id, (string) $post['title'], $status, (string) $post['status']);
        }

        return $updated;
    }

    private function notifyIfPublished(int $newsId, string $title, string $newStatus, ?string $previousStatus): void
    {
        if ($newStatus !== 'published' || $previousStatus === 'published') {
            return;
        }

        $this->discord->notifyNewsPublished($newsId, $title);
    }
}
