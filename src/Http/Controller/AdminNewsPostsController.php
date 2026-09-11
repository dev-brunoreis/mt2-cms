<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminNewsPostsController extends AdminNewsBaseController
{
    public function index(): Response
    {
        $spec = $this->news->gridDefinition()->spec();
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
            $this->news->gridDefinition()->spec(),
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
            ]);
            $this->audit('news.create', 'news', $newsId);
            $this->flash('success', $this->t('admin.news.created'));

            return $this->redirect('/admin/content/news?tab=posts');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
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
            'status' => (string) $post['status'],
            'comments_enabled' => (int) $post['comments_enabled'] === 1,
            'author_login' => (string) $post['author_login'],
            'views' => (int) $post['views'],
            'published_at' => $post['published_at'],
        ]);
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
            $this->flash('success', $this->t('admin.news.update_ok'));

            return $this->redirect('/admin/content/news/posts/' . (int) $id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
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
        if (!$this->adminAuth->check()) {
            return Response::json(['error' => $this->t('admin.login_required')], 401);
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
    private function formView(array $post = [], ?string $error = null, int $status = 200): Response
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
        ], $status);
    }

    /**
     * @return array{title: string, body: string, cover_image: string, status: string, comments_enabled: bool}
     */
    private function prefill(): array
    {
        return [
            'title' => '',
            'body' => '',
            'cover_image' => '',
            'status' => 'draft',
            'comments_enabled' => true,
        ];
    }

    /**
     * @return array{title: string, body: string, cover_image: ?string, status: string, comments_enabled: bool}
     */
    private function formInput(): array
    {
        $cover = trim((string) ($_POST['cover_image'] ?? ''));
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
        ];
    }

    /**
     * @param array{title: string, body: string, cover_image: ?string, status: string, comments_enabled: bool} $input
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

    private function setNewsStatus(int $id, string $status): bool
    {
        $post = $this->news->findById($id);

        if ($post === null) {
            return false;
        }

        return $this->news->update($id, [
            'title' => (string) $post['title'],
            'body' => (string) $post['body'],
            'cover_image' => $post['cover_image'],
            'status' => $status,
            'comments_enabled' => (int) $post['comments_enabled'] === 1,
        ]);
    }
}
