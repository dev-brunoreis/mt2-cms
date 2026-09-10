<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridRequest;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\AdminAuditService;
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
        protected AdminAuditService $auditLog,
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

        if ($deny = $this->denyUnlessSectionAllowed($section)) {
            return $deny;
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

    protected function gridQuery(GridSpec $spec): GridQuery
    {
        return GridRequest::fromGet($spec);
    }

    /**
     * @return list<int>
     */
    protected function gridMassIds(int $max = 100): array
    {
        return GridRequest::massIds($max);
    }

    protected function gridMassAction(): string
    {
        return GridRequest::massAction();
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    protected function audit(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $meta = null,
    ): void {
        $this->auditLog->record($action, $targetType, $targetId, $meta);
    }

    /**
     * @param array<string, callable(int): bool> $handlers action id => handler (return true on success)
     */
    protected function runMassActions(
        GridSpec $spec,
        string $redirect,
        array $handlers,
        string $targetType,
        string $successKey,
    ): Response {
        if ($redirectResponse = $this->requireAdmin()) {
            return $redirectResponse;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($redirect);
        }

        $action = $this->gridMassAction();
        $ids = $this->gridMassIds();

        if ($ids === [] || $action === '') {
            $this->flash('error', $this->t('admin.grid.no_selection'));

            return $this->redirect($redirect);
        }

        if (!$this->isAllowedMassAction($spec, $action) || !isset($handlers[$action])) {
            $this->flash('error', $this->t('admin.grid.invalid_action'));

            return $this->redirect($redirect);
        }

        $handler = $handlers[$action];
        $count = 0;
        $succeeded = [];

        foreach ($ids as $id) {
            try {
                if ($handler($id)) {
                    $count++;
                    $succeeded[] = $id;
                }
            } catch (\InvalidArgumentException | \RuntimeException) {
                continue;
            }
        }

        if ($count > 0) {
            $this->audit('admin.grid.mass', $targetType, null, [
                'action' => $action,
                'ids' => $succeeded,
                'count' => $count,
            ]);
        }

        $this->flash('success', $this->t($successKey, ['count' => $count]));

        return $this->redirect($redirect);
    }

    protected function denyUnlessSectionAllowed(string $section): ?Response
    {
        if (AdminPermissions::canAccessSection($this->adminAuth->role(), $section)) {
            return null;
        }

        $this->flash('error', $this->t('admin.access_denied'));

        return $this->redirect('/admin');
    }

    private function isAllowedMassAction(GridSpec $spec, string $action): bool
    {
        foreach ($spec->massActions as $entry) {
            if (($entry['id'] ?? '') === $action) {
                return true;
            }
        }

        return false;
    }
}
