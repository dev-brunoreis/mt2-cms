<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\NewsCommentsGrid;
use Mt2Cms\Admin\Grid\Definitions\NewsGrid;
use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminNewsHubController extends AdminNewsBaseController
{
    private const TABS = ['posts', 'comments'];

    /** @var array<string, string> */
    private const TAB_VIEW_RESOURCES = [
        'posts' => 'content/news/posts/view',
        'comments' => 'content/news/comments/view',
    ];

    public function index(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if ((string) ($_GET['tab'] ?? '') === 'settings') {
            return $this->redirect(AdminPaths::settingsNews());
        }

        $tab = $this->resolveResourceTab(self::TABS, self::TAB_VIEW_RESOURCES, 'posts');

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        $header = $tab === 'posts'
            ? [
                'headerHref' => AdminPaths::contentNewsPostNew(),
                'headerActionLabel' => $this->t('admin.news.create'),
            ]
            : [];

        return $this->adminView('news', 'pages/news-hub.twig', array_merge([
            'title' => $this->t('admin.news.hub_title'),
            'pageLead' => $this->t('admin.news.hub_lead'),
            'activeTab' => $tab,
            'newsBaseUrl' => AdminPaths::contentNews(),
            'initialPartial' => $this->partialPayload($tab),
        ], $header));
    }

    private function renderTabPartial(string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            return new Response('', 404);
        }

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        $payload = $this->partialPayload($tab);

        return $this->adminFragment($payload['template'], $payload['data']);
    }

    /**
     * @return array{template: string, data: array<string, mixed>}
     */
    private function partialPayload(string $tab): array
    {
        return match ($tab) {
            'comments' => [
                'template' => 'pages/news-comments-partial.twig',
                'data' => ['grid' => $this->commentsGrid()],
            ],
            default => [
                'template' => 'pages/news-posts-partial.twig',
                'data' => ['grid' => $this->postsGrid()],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function postsGrid(): array
    {
        $spec = NewsGrid::definition()->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->news->countForGrid($q),
            fn ($q) => $this->news->listForGrid($q),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function commentsGrid(): array
    {
        $spec = NewsCommentsGrid::definition()->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->comments->countForGrid($q),
            fn ($q) => $this->comments->listForGrid($q),
        );
    }
}
