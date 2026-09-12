<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Captcha;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Auth\Totp;
use Mt2Cms\Http\Controller\Controller;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AdminTotpRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminAuthController extends Controller
{
    private RateLimiter $rateLimiter;
    private Captcha $captcha;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AdminAuth $adminAuth,
        private ThemeEngine $adminTheme,
        private AclService $acl,
        private SettingsService $settings,
        private AdminTotpRepository $totp,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->captcha = new Captcha('admin');
    }

    public function showLogin(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect($this->landingPath());
        }

        if ($this->adminAuth->pendingTwoFactor() !== null) {
            return $this->redirect('/admin/login/2fa');
        }

        return $this->loginForm();
    }

    public function login(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect($this->landingPath());
        }

        if (!$this->assertCsrf()) {
            return $this->loginForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        if ($this->settings->captchaAdminEnabled()) {
            if (!$this->captcha->verify($_POST['captcha'] ?? null)) {
                $this->rateLimiter->hit($this->authBucket('admin-captcha'));

                return $this->loginForm(
                    username: trim((string) ($_POST['username'] ?? '')),
                    error: $this->t('auth.invalid_captcha'),
                    status: 422,
                );
            }
        }

        $bucket = $this->authBucket('admin-login');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->loginForm(error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $admin = $this->adminAuth->verifyPassword($username, $password);

        if ($admin === null) {
            $this->rateLimiter->hit($bucket);

            return $this->loginForm(
                username: $username,
                error: $this->t('admin.invalid_credentials'),
                status: 401,
            );
        }

        $this->rateLimiter->clear($bucket);

        if ($admin['totp_enabled']) {
            $this->adminAuth->setPendingTwoFactor($admin['id'], $admin['login']);

            return $this->redirect('/admin/login/2fa');
        }

        $this->adminAuth->completeLogin($admin);
        $this->flash('success', $this->t('admin.welcome_back'));

        if ($this->settings->adminTwoFactorRequired()) {
            return $this->redirect('/admin/account/security');
        }

        return $this->redirect($this->landingPath());
    }

    public function showTwoFactor(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect($this->landingPath());
        }

        if ($this->adminAuth->pendingTwoFactor() === null) {
            return $this->redirect('/admin/login');
        }

        return Response::html($this->adminTheme->render('login-2fa', [
            'title' => $this->t('admin.2fa.login_title'),
            'error' => null,
            'csrf' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
        ]));
    }

    public function verifyTwoFactor(): Response
    {
        if ($this->adminAuth->check()) {
            return $this->redirect($this->landingPath());
        }

        if (!$this->assertCsrf()) {
            return $this->twoFactorForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $pending = $this->adminAuth->pendingTwoFactor();

        if ($pending === null) {
            return $this->redirect('/admin/login');
        }

        $bucket = $this->authBucket('admin-2fa');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->twoFactorForm(error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $recovery = trim((string) ($_POST['recovery_code'] ?? ''));
        $verified = false;

        if ($recovery !== '') {
            $verified = $this->totp->verifyRecoveryCode($pending['id'], $recovery);
        } elseif ($code !== '') {
            $secret = $this->totp->secretFor($pending['id']);
            $verified = $secret !== null && Totp::verify($secret, $code);
        }

        if (!$verified) {
            $this->rateLimiter->hit($bucket);

            return $this->twoFactorForm(error: $this->t('admin.2fa.invalid_code'), status: 401);
        }

        $this->rateLimiter->clear($bucket);
        $this->adminAuth->completeLogin([
            'id' => $pending['id'],
            'login' => $pending['login'],
        ]);
        $this->flash('success', $this->t('admin.welcome_back'));

        return $this->redirect($this->landingPath());
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

    private function landingPath(): string
    {
        return $this->acl->firstAccessiblePath($this->adminAuth->user()) ?? '/admin/login';
    }

    private function authBucket(string $action): string
    {
        return $action . ':' . Request::clientIp();
    }

    private function loginForm(
        string $username = '',
        ?string $error = null,
        int $status = 200,
    ): Response {
        return Response::html($this->adminTheme->render('login', [
            'title' => $this->t('admin.login_title'),
            'username' => $username,
            'error' => $error,
            'csrf' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
            'captchaEnabled' => $this->settings->captchaAdminEnabled(),
        ]), $status);
    }

    private function twoFactorForm(?string $error = null, int $status = 200): Response
    {
        return Response::html($this->adminTheme->render('login-2fa', [
            'title' => $this->t('admin.2fa.login_title'),
            'error' => $error,
            'csrf' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
        ]), $status);
    }
}
