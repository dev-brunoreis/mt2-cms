<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

class AdminAuthController extends Controller
{
    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AdminAuth $adminAuth,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function showLogin(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect('/admin');
        }

        return $this->view('admin-login', [
            'title' => $this->t('admin.login_title'),
        ]);
    }

    public function login(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect('/admin');
        }

        if (!$this->assertCsrf()) {
            return $this->loginForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $bucket = $this->authBucket('admin-login');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->loginForm(error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->adminAuth->attempt($username, $password)) {
            $this->rateLimiter->hit($bucket);

            return $this->loginForm(
                username: $username,
                error: $this->t('admin.invalid_credentials'),
                status: 401,
            );
        }

        $this->rateLimiter->clear($bucket);
        $this->flash('success', $this->t('admin.welcome_back'));

        return $this->redirect('/admin');
    }

    public function logout(): Response
    {
        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf_short'));

            return $this->redirect('/admin/login');
        }

        $this->adminAuth->logout();
        $this->flash('success', $this->t('admin.logged_out'));

        return $this->redirect('/admin/login');
    }

    private function authBucket(string $action): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return $action . ':' . $ip;
    }

    private function loginForm(
        string $username = '',
        ?string $error = null,
        int $status = 200,
    ): Response {
        return $this->view('admin-login', [
            'title' => $this->t('admin.login_title'),
            'username' => $username,
            'error' => $error,
        ], $status);
    }
}
