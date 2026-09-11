<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Captcha;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class PasswordController extends Controller
{
    private RateLimiter $rateLimiter;
    private Captcha $captcha;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AccountEmailService $emails,
        private MailerInterface $mailer,
        private SettingsService $settings,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->captcha = new Captcha('public');
    }

    public function showForgot(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->view('forgot-password', [
            'title' => $this->t('auth.forgot_title'),
            'error' => null,
            'sent' => false,
            'captchaEnabled' => $this->settings->captchaPublicEnabled(),
        ]);
    }

    public function forgot(): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->forgotForm(error: $this->t('auth.invalid_csrf'), status: 400);
        }

        if ($this->settings->captchaPublicEnabled() && !$this->captcha->verify($_POST['captcha'] ?? null)) {
            return $this->forgotForm(error: $this->t('auth.invalid_captcha'), status: 422);
        }

        $bucket = 'forgot:' . Request::clientIp();

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->forgotForm(error: $this->t('auth.too_many_attempts'), status: 429);
        }

        if (!$this->mailer->isConfigured()) {
            return $this->forgotForm(error: $this->t('auth.mail_not_configured'), status: 503);
        }

        $loginOrEmail = trim((string) ($_POST['login_or_email'] ?? ''));

        try {
            $this->emails->requestPasswordReset($loginOrEmail);
        } catch (\RuntimeException) {
            $this->rateLimiter->hit($bucket);

            return $this->forgotForm(error: $this->t('auth.mail_send_failed'), status: 503);
        }

        $this->rateLimiter->hit($bucket);

        return $this->view('forgot-password', [
            'title' => $this->t('auth.forgot_title'),
            'error' => null,
            'sent' => true,
            'captchaEnabled' => $this->settings->captchaPublicEnabled(),
        ]);
    }

    public function showReset(string $token): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        return $this->view('reset-password', [
            'title' => $this->t('auth.reset_title'),
            'token' => $token,
            'error' => null,
        ]);
    }

    public function reset(string $token): Response
    {
        if ($redirect = $this->requireGuest()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return $this->resetForm($token, error: $this->t('auth.invalid_csrf'), status: 400);
        }

        $bucket = 'reset:' . Request::clientIp();

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $this->resetForm($token, error: $this->t('auth.too_many_attempts'), status: 429);
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!hash_equals($password, $confirm)) {
            $this->rateLimiter->hit($bucket);

            return $this->resetForm($token, error: $this->t('account.password_mismatch'), status: 422);
        }

        if (!$this->emails->resetPassword($token, $password)) {
            $this->rateLimiter->hit($bucket);

            return $this->resetForm($token, error: $this->t('auth.reset_invalid'), status: 422);
        }

        $this->rateLimiter->clear($bucket);
        $this->flash('success', $this->t('auth.reset_success'));

        return $this->redirect('/login');
    }

    private function forgotForm(?string $error = null, int $status = 200): Response
    {
        return $this->view('forgot-password', [
            'title' => $this->t('auth.forgot_title'),
            'error' => $error,
            'sent' => false,
            'captchaEnabled' => $this->settings->captchaPublicEnabled(),
        ], $status);
    }

    private function resetForm(string $token, ?string $error = null, int $status = 200): Response
    {
        return $this->view('reset-password', [
            'title' => $this->t('auth.reset_title'),
            'token' => $token,
            'error' => $error,
        ], $status);
    }
}
