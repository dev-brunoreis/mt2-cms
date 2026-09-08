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
        protected ThemeEngine $adminTheme,
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

        return $this->renderAdmin('panel', array_merge([
            'activeSection' => $section,
            'activeGroup' => \Mt2Cms\Admin\AdminSections::groupForSection($section),
            'contentTemplate' => $contentTemplate,
            'adminUser' => $this->adminAuth->user(),
        ], $data), $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function renderAdmin(string $layout, array $data = [], int $status = 200): Response
    {
        $data = array_merge([
            'csrf' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
        ], $data);

        return Response::html($this->adminTheme->render($layout, $data), $status);
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
