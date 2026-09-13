<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\NewsCommentRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class NewsController extends Controller
{
    private const PER_PAGE = 10;
    private const SESSION_VIEWS = '_news_views';

    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private NewsRepository $news,
        private NewsCommentRepository $comments,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = new RateLimiter(10, 900);
    }

    public function index(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->news->countPublished($query);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->view('news', [
            'title' => $this->t('news.title'),
            'posts' => $this->news->listPublished($page, self::PER_PAGE, $query),
            'query' => $q,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function show(string $id): Response
    {
        $post = $this->news->findPublishedById((int) $id);

        if ($post === null) {
            return $this->view('news-show', [
                'title' => $this->t('news.not_found'),
                'post' => null,
                'notFound' => true,
                'seo' => ['noindex' => true],
            ], 404);
        }

        $this->recordView((int) $post['id']);
        $post = $this->news->findPublishedById((int) $id) ?? $post;

        $accountId = $this->auth->id();
        $commentsEnabled = $this->settings->newsCommentsEnabled()
            && (int) ($post['comments_enabled'] ?? 0) === 1;

        return $this->view('news-show', [
            'title' => (string) $post['title'],
            'seo' => $this->seoForPost($post),
            'post' => $post,
            'notFound' => false,
            'comments' => $this->comments->listVisibleForNews((int) $post['id'], $accountId),
            'commentsEnabled' => $commentsEnabled,
            'commentsRequireApproval' => $this->settings->newsCommentsRequireApproval(),
            'canComment' => $commentsEnabled && $this->auth->check(),
        ]);
    }

    public function comment(string $id): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/news/' . (int) $id);
        }

        $post = $this->news->findPublishedById((int) $id);

        if ($post === null) {
            $this->flash('error', $this->t('news.not_found'));

            return $this->redirect('/news');
        }

        $commentsEnabled = $this->settings->newsCommentsEnabled()
            && (int) ($post['comments_enabled'] ?? 0) === 1;

        if (!$commentsEnabled) {
            $this->flash('error', $this->t('news.comments_disabled'));

            return $this->redirect('/news/' . (int) $id);
        }

        $bucket = 'news_comment:' . ($this->auth->id() ?? 0) . ':' . $this->clientIp();

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            $this->flash('error', $this->t('news.comment_rate_limited'));

            return $this->redirect('/news/' . (int) $id);
        }

        $body = trim((string) ($_POST['body'] ?? ''));

        if ($body === '' || mb_strlen($body) > 2000) {
            $this->flash('error', $this->t('news.comment_invalid'));

            return $this->redirect('/news/' . (int) $id);
        }

        $status = $this->settings->newsCommentsRequireApproval() ? 'pending' : 'approved';
        $this->comments->create(
            (int) $post['id'],
            (int) $this->auth->id(),
            (string) $this->auth->login(),
            $body,
            $status,
        );
        $this->rateLimiter->hit($bucket);

        $this->flash(
            'success',
            $status === 'pending'
                ? $this->t('news.comment_pending')
                : $this->t('news.comment_posted'),
        );

        return $this->redirect('/news/' . (int) $id);
    }

    private function recordView(int $newsId): void
    {
        $views = $_SESSION[self::SESSION_VIEWS] ?? [];

        if (!is_array($views)) {
            $views = [];
        }

        $key = (string) $newsId;

        if (isset($views[$key])) {
            return;
        }

        $this->news->incrementViews($newsId);
        $views[$key] = time();
        $_SESSION[self::SESSION_VIEWS] = $views;
    }

    private function clientIp(): string
    {
        return Request::clientIp();
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function seoForPost(array $post): array
    {
        $image = trim((string) ($post['seo_og_image'] ?? ''));

        if ($image === '') {
            $image = trim((string) ($post['cover_image'] ?? ''));
        }

        return [
            'title' => trim((string) ($post['seo_title'] ?? '')),
            'description' => trim((string) ($post['seo_description'] ?? '')),
            'excerpt_html' => (string) ($post['body'] ?? ''),
            'image' => $image,
            'type' => 'article',
            'headline' => (string) ($post['title'] ?? ''),
            'published_at' => $post['published_at'] ?? null,
            'modified_at' => $post['updated_at'] ?? null,
        ];
    }
}
