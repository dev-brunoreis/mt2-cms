<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class AccountController extends Controller
{
    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PlayerRepository $players,
        private AccountRepository $accounts,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();
        $account = $this->auth->user();

        if ($account !== null && $accountId !== null) {
            $account['empire'] = $this->players->findEmpireByAccountId($accountId);
        }

        return $this->view('account', [
            'title' => $this->t('account.title'),
            'account' => $account,
        ]);
    }

    public function characters(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();
        $players = $accountId !== null
            ? $this->players->findByAccountId($accountId)
            : [];

        return $this->view('characters', [
            'title' => $this->t('account.characters'),
            'players' => $players,
        ]);
    }

    public function showPassword(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        return $this->view('account-password', [
            'title' => $this->t('account.password_title'),
            'error' => null,
        ]);
    }

    public function updatePassword(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->passwordForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $accountId = $this->auth->id();

        if ($accountId === null) {
            return $this->redirect('/login');
        }

        $bucket = 'account-password:' . $accountId;

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->passwordForm(error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!hash_equals($password, $confirm)) {
            $this->rateLimiter->hit($bucket);

            return $this->passwordForm(error: $this->t('account.password_mismatch'), status: 422);
        }

        try {
            $this->accounts->changePassword($accountId, $current, $password);
        } catch (\InvalidArgumentException $e) {
            $this->rateLimiter->hit($bucket);

            return $this->passwordForm(error: $this->t($e->getMessage()), status: 422);
        } catch (\RuntimeException) {
            $this->rateLimiter->hit($bucket);

            return $this->passwordForm(error: $this->t('account.password_failed'), status: 422);
        }

        $this->rateLimiter->clear($bucket);
        session_regenerate_id(true);
        $this->flash('success', $this->t('account.password_changed'));

        return $this->redirect('/account/password');
    }

    private function passwordForm(?string $error = null, int $status = 200): Response
    {
        return $this->view('account-password', [
            'title' => $this->t('account.password_title'),
            'error' => $error,
        ], $status);
    }
}
