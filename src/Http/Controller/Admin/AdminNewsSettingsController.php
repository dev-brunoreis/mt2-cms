<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Http\Response;

class AdminNewsSettingsController extends AdminNewsBaseController
{
    public function settings(): Response
    {
        return $this->adminView('news', 'pages/news-settings.twig', [
            'title' => $this->t('admin.news.settings_title'),
            'pageLead' => $this->t('admin.news.settings_lead'),
            'formId' => 'admin-news-settings-form',
            'commentsEnabled' => $this->settings->newsCommentsEnabled(),
            'commentsRequireApproval' => $this->settings->newsCommentsRequireApproval(),
            'showViews' => $this->settings->newsShowViews(),
        ]);
    }

    public function saveSettings(): Response
    {
        if ($redirect = $this->requireAdminResource('content/news/settings/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::contentNews('settings'));
        }

        $before = [
            'news_comments_enabled' => $this->settings->newsCommentsEnabled(),
            'news_comments_require_approval' => $this->settings->newsCommentsRequireApproval(),
            'news_show_views' => $this->settings->newsShowViews(),
        ];
        $after = [
            'news_comments_enabled' => isset($_POST['news_comments_enabled']),
            'news_comments_require_approval' => isset($_POST['news_comments_require_approval']),
            'news_show_views' => isset($_POST['news_show_views']),
        ];
        $this->settings->setNewsCommentsEnabled($after['news_comments_enabled']);
        $this->settings->setNewsCommentsRequireApproval($after['news_comments_require_approval']);
        $this->settings->setNewsShowViews($after['news_show_views']);
        $this->auditChange('news.settings_save', 'news_settings', null, $before, $after);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::contentNews('settings'));
    }
}
