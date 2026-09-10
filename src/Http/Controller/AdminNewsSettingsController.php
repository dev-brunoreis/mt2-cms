<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

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
        ]);
    }

    public function saveSettings(): Response
    {
        if ($redirect = $this->requireAdminSection('news')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::contentNews('settings'));
        }

        $this->settings->setNewsCommentsEnabled(isset($_POST['news_comments_enabled']));
        $this->settings->setNewsCommentsRequireApproval(isset($_POST['news_comments_require_approval']));
        $this->audit('news.settings_save', 'news_settings', null);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::contentNews('settings'));
    }
}
