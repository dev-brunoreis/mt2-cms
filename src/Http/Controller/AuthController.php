<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AuthController extends Controller
{
    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AccountRepository $accounts,
        private SettingsService $settings,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function showLogin(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->authForm('login');
    }

    public function login(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->authForm('login', error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $bucket = $this->authBucket('login');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->authForm('login', error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->auth->attempt($username, $password)) {
            $this->rateLimiter->hit($bucket);

            return $this->authForm(
                'login',
                username: $username,
                error: $this->t('auth.invalid_credentials'),
                status: 401,
            );
        }

        $this->rateLimiter->clear($bucket);
        $this->flash('success', $this->t('auth.welcome_back'));

        return $this->redirect('/account');
    }

    public function showRegister(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->settings->registrationEnabled()) {
            return $this->authForm('register', registrationBlocked: true);
        }

        return $this->authForm('register');
    }

    public function register(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->settings->registrationEnabled()) {
            return $this->authForm('register', registrationBlocked: true, status: 403);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $socialId = trim((string) ($_POST['social_id'] ?? ''));

        if (!$this->assertCsrf()) {
            return $this->authForm(
                'register',
                username: $username,
                email: $email,
                socialId: $socialId,
                error: $this->t('auth.invalid_csrf'),
                status: 400,
            );
        }

        $bucket = $this->authBucket('register');

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->authForm(
                'register',
                username: $username,
                email: $email,
                socialId: $socialId,
                error: $this->t('auth.too_many_attempts'),
                status: 429,
            );
        }

        try {
            $this->accounts->create($username, $email, $password, $socialId);
            $this->auth->attempt($username, $password);
            $this->rateLimiter->clear($bucket);
            $this->flash('success', $this->t('auth.account_created'));

            return $this->redirect('/account');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->rateLimiter->hit($bucket);

            return $this->authForm(
                'register',
                username: $username,
                email: $email,
                socialId: $socialId,
                error: $this->t($e->getMessage()),
                status: 422,
            );
        }
    }

    public function logout(): Response
    {
        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf_short'));

            return $this->redirect('/');
        }

        $this->auth->logout();
        $this->flash('success', $this->t('auth.logged_out'));

        return $this->redirect('/');
    }

    private function authBucket(string $action): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        return $action . ':' . $ip;
    }

    private function authForm(
        string $form,
        string $username = '',
        string $email = '',
        string $socialId = '',
        ?string $error = null,
        bool $registrationBlocked = false,
        int $status = 200,
    ): Response {
        return $this->view('auth', [
            'title' => $this->t($form === 'login' ? 'auth.login_title' : 'auth.register_title'),
            'form' => $form,
            'username' => $username,
            'email' => $email,
            'socialId' => $socialId,
            'error' => $error,
            'registrationBlocked' => $registrationBlocked,
        ], $status);
    }
}
