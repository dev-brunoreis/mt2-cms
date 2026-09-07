<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Theme\ThemeEngine;

class AuthController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
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

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->auth->attempt($username, $password)) {
            return $this->authForm(
                'login',
                username: $username,
                error: $this->t('auth.invalid_credentials'),
                status: 401,
            );
        }

        $this->flash('success', $this->t('auth.welcome_back'));

        return $this->redirect('/account');
    }

    public function showRegister(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->authForm('register');
    }

    public function register(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
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

        try {
            $this->accounts->create($username, $email, $password, $socialId);
            $this->auth->attempt($username, $password);
            $this->flash('success', $this->t('auth.account_created'));

            return $this->redirect('/account');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
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

    private function authForm(
        string $form,
        string $username = '',
        string $email = '',
        string $socialId = '',
        ?string $error = null,
        int $status = 200,
    ): Response {
        return $this->view('auth', [
            'title' => $this->t($form === 'login' ? 'auth.login_title' : 'auth.register_title'),
            'form' => $form,
            'username' => $username,
            'email' => $email,
            'socialId' => $socialId,
            'error' => $error,
        ], $status);
    }
}
