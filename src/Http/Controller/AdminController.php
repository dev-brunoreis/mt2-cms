<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

abstract class AdminController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        protected AdminAuth $adminAuth,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function adminView(string $section, string $contentTemplate, array $data = [], int $status = 200): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        return $this->view('admin', array_merge([
            'title' => $this->t('admin.title'),
            'activeSection' => $section,
            'contentTemplate' => $contentTemplate,
            'adminUser' => $this->adminAuth->user(),
        ], $data), $status);
    }

    protected function requireAdmin(): ?Response
    {
        if ($this->adminAuth->check()) {
            return null;
        }

        $this->flash('error', $this->t('admin.login_required'));

        return $this->redirect('/admin/login');
    }
}
