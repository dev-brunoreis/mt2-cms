<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\ReferralService;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;
use Mt2Cms\Service\UnstuckService;

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
        private AccountEmailService $accountEmails,
        private ItemShopOrderRepository $shopOrders,
        private PaymentRepository $payments,
        private MailerInterface $mailer,
        private SettingsService $settings,
        private UnstuckService $unstuck,
        private ReferralService $referrals,
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
            $account['email'] = $this->accounts->findEmailById($accountId);
            $account['email_verified'] = $this->accountEmails->isVerified($accountId);
            $this->referrals->processPendingReward($accountId);
        }

        $referralCode = null;

        if ($accountId !== null && $this->referrals->isEnabled()) {
            $referralCode = $this->referrals->ensureCodeForAccount($accountId);
        }

        return $this->view('account', [
            'title' => $this->t('account.title'),
            'account' => $account,
            'mailConfigured' => $this->mailer->isConfigured(),
            'referralCode' => $referralCode,
            'referralEnabled' => $this->referrals->isEnabled(),
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

        if ($accountId !== null) {
            $this->referrals->processPendingReward($accountId);
        }

        $unstuckStates = [];

        if ($accountId !== null && $this->unstuck->isAvailable()) {
            foreach ($players as $player) {
                $playerId = (int) ($player['id'] ?? 0);

                if ($playerId < 1) {
                    continue;
                }

                $unstuckStates[$playerId] = [
                    'offline' => $this->unstuck->isOffline($playerId),
                    'cooldown_seconds' => $this->unstuck->cooldownRemainingSeconds($playerId),
                ];
            }
        }

        return $this->view('characters', [
            'title' => $this->t('account.characters'),
            'players' => $players,
            'unstuckAvailable' => $this->unstuck->isAvailable(),
            'unstuckStates' => $unstuckStates,
            'onlineWindowMinutes' => $this->settings->onlineWindowMinutes(),
        ]);
    }

    public function unstuck(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($this->unstuckRedirect((int) ($_POST['player_id'] ?? 0), $this->auth->id()));
        }

        $accountId = $this->auth->id();

        if ($accountId === null) {
            return $this->redirect('/login');
        }

        $bucket = 'account-unstuck:' . $accountId;
        $playerId = (int) ($_POST['player_id'] ?? 0);

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            $this->flash('error', $this->t('unstuck.rate_limited'));

            return $this->redirect($this->unstuckRedirect($playerId, $accountId));
        }

        $this->rateLimiter->hit($bucket);

        try {
            $this->unstuck->unstuck($playerId, $accountId);
            $this->rateLimiter->clear($bucket);
            $this->flash('success', $this->t('unstuck.success'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect($this->unstuckRedirect($playerId, $accountId));
    }

    private function unstuckRedirect(int $playerId, ?int $accountId): string
    {
        if (($_POST['return_to'] ?? '') !== 'player' || $playerId < 1 || $accountId === null) {
            return '/account/characters';
        }

        $player = $this->players->findById($playerId);

        if ($player === null || (int) ($player['account_id'] ?? 0) !== $accountId) {
            return '/account/characters';
        }

        $name = (string) ($player['name'] ?? '');

        if ($name === '') {
            return '/account/characters';
        }

        return '/player/' . rawurlencode($name);
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

    public function showEmail(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();

        return $this->view('account-email', [
            'title' => $this->t('account.email_title'),
            'email' => $accountId !== null ? $this->accounts->findEmailById($accountId) : '',
            'error' => null,
            'mailConfigured' => $this->mailer->isConfigured(),
        ]);
    }

    public function updateEmail(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->emailForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $accountId = $this->auth->id();

        if ($accountId === null) {
            return $this->redirect('/login');
        }

        if (!$this->mailer->isConfigured()) {
            return $this->emailForm(error: $this->t('auth.mail_not_configured'), status: 503);
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $newEmail = trim((string) ($_POST['email'] ?? ''));

        if (!$this->accounts->verifyPassword($accountId, $current)) {
            return $this->emailForm(error: $this->t('account.wrong_password'), status: 422);
        }

        try {
            $this->accountEmails->requestEmailChange($accountId, $newEmail);
            $this->flash('success', $this->t('account.email_change_sent'));

            return $this->redirect('/account/email');
        } catch (\InvalidArgumentException $e) {
            return $this->emailForm(error: $this->t($e->getMessage()), status: 422);
        } catch (\RuntimeException) {
            return $this->emailForm(error: $this->t('auth.mail_send_failed'), status: 503);
        }
    }

    public function showPin(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        return $this->view('account-pin', [
            'title' => $this->t('account.pin_title'),
            'error' => null,
        ]);
    }

    public function updatePin(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->pinForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $accountId = $this->auth->id();

        if ($accountId === null) {
            return $this->redirect('/login');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $pin = trim((string) ($_POST['social_id'] ?? ''));

        try {
            $this->accounts->changeSocialId($accountId, $current, $pin);
            $this->flash('success', $this->t('account.pin_changed'));

            return $this->redirect('/account/pin');
        } catch (\InvalidArgumentException $e) {
            return $this->pinForm(error: $this->t($e->getMessage()), status: 422);
        }
    }

    public function orders(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();

        return $this->view('account-orders', [
            'title' => $this->t('account.orders_title'),
            'orders' => $accountId !== null ? $this->shopOrders->listByAccountId($accountId) : [],
        ]);
    }

    public function payments(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();

        return $this->view('account-payments', [
            'title' => $this->t('account.payments_title'),
            'payments' => $accountId !== null ? $this->payments->listByAccountId($accountId) : [],
        ]);
    }

    private function emailForm(?string $error = null, int $status = 200): Response
    {
        $accountId = $this->auth->id();

        return $this->view('account-email', [
            'title' => $this->t('account.email_title'),
            'email' => $accountId !== null ? $this->accounts->findEmailById($accountId) : '',
            'error' => $error,
            'mailConfigured' => $this->mailer->isConfigured(),
        ], $status);
    }

    private function pinForm(?string $error = null, int $status = 200): Response
    {
        return $this->view('account-pin', [
            'title' => $this->t('account.pin_title'),
            'error' => $error,
        ], $status);
    }
}
