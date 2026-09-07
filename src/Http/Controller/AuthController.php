<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Theme\ThemeEngine;

class AuthController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf);
    }

    public function showLogin(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->view('auth', [
            'title' => 'Login',
            'form' => 'login',
            'username' => '',
            'email' => '',
            'socialId' => '',
            'error' => null,
        ]);
    }

    public function login(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->view('auth', [
                'title' => 'Login',
                'form' => 'login',
                'username' => '',
                'email' => '',
                'socialId' => '',
                'error' => 'Invalid security token. Please try again.',
            ], 400);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->auth->attempt($username, $password)) {
            return $this->view('auth', [
                'title' => 'Login',
                'form' => 'login',
                'username' => $username,
                'email' => '',
                'socialId' => '',
                'error' => 'Invalid username or password.',
            ], 401);
        }

        $this->flash('success', 'Welcome back.');

        return $this->redirect('/account');
    }

    public function showRegister(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->view('auth', [
            'title' => 'Create Account',
            'form' => 'register',
            'username' => '',
            'email' => '',
            'socialId' => '',
            'error' => null,
        ]);
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
            return $this->view('auth', [
                'title' => 'Create Account',
                'form' => 'register',
                'username' => $username,
                'email' => $email,
                'socialId' => $socialId,
                'error' => 'Invalid security token. Please try again.',
            ], 400);
        }

        try {
            $this->accounts->create($username, $email, $password, $socialId);
            $this->auth->attempt($username, $password);
            $this->flash('success', 'Account created successfully.');

            return $this->redirect('/account');
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->view('auth', [
                'title' => 'Create Account',
                'form' => 'register',
                'username' => $username,
                'email' => $email,
                'socialId' => $socialId,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function logout(): Response
    {
        if (!$this->assertCsrf()) {
            $this->flash('error', 'Invalid security token.');

            return $this->redirect('/');
        }

        $this->auth->logout();
        $this->flash('success', 'You have been logged out.');

        return $this->redirect('/');
    }
}
