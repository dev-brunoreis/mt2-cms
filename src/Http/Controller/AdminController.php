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

    protected function denyUnlessAdmin(): ?Response
    {
        if ($this->adminAuth->check()) {
            return null;
        }

        if ($this->wantsTabPartial()) {
            return new Response('', 401);
        }

        return $this->requireAdmin();
    }

    /**
     * @param list<string> $allowed
     */
    protected function requestedTab(array $allowed, string $default): string
    {
        $tab = (string) ($_GET['tab'] ?? '');

        return in_array($tab, $allowed, true) ? $tab : $default;
    }

    protected function wantsTabPartial(): bool
    {
        return (string) ($_GET['partial'] ?? '') === '1';
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function adminFragment(string $template, array $data = [], int $status = 200): Response
    {
        if (!$this->adminAuth->check()) {
            return new Response('', 401);
        }

        return Response::html($this->adminTheme->renderTemplate($template, $data), $status);
    }
}
