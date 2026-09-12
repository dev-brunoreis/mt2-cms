<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminNewsHubController extends AdminNewsBaseController
{
    private const TABS = ['posts', 'comments', 'settings'];

    /** @var array<string, string> */
    private const TAB_VIEW_RESOURCES = [
        'posts' => 'content/news/posts/view',
        'comments' => 'content/news/comments/view',
        'settings' => 'content/news/settings/view',
    ];

    public function index(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $tab = $this->resolveResourceTab(self::TABS, self::TAB_VIEW_RESOURCES, 'posts');

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        $header = match ($tab) {
            'posts' => [
                'headerHref' => AdminPaths::contentNewsPostNew(),
                'headerActionLabel' => $this->t('admin.news.create'),
            ],
            'settings' => [
                'formId' => 'admin-news-settings-form',
            ],
            default => [],
        };

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
            'settings' => [
                'template' => 'pages/news-settings-partial.twig',
                'data' => [
                    'formId' => 'admin-news-settings-form',
                    'commentsEnabled' => $this->settings->newsCommentsEnabled(),
                    'commentsRequireApproval' => $this->settings->newsCommentsRequireApproval(),
                ],
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
        $spec = $this->news->gridDefinition()->spec();
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
        $spec = $this->comments->gridDefinition()->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->comments->countForGrid($q),
            fn ($q) => $this->comments->listForGrid($q),
        );
    }
}
