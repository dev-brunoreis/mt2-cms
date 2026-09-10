<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Theme\ThemeEngine;

class EmailVerificationController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private AccountEmailService $emails,
        private MailerInterface $mailer,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function verify(string $token): Response
    {
        if ($this->emails->verify($token)) {
            $this->flash('success', $this->t('auth.email_verified'));

            return $this->redirect($this->auth->check() ? '/account' : '/login');
        }

        $this->flash('error', $this->t('auth.verify_invalid'));

        return $this->redirect('/login');
    }

    public function resend(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/account');
        }

        if (!$this->mailer->isConfigured()) {
            $this->flash('error', $this->t('auth.mail_not_configured'));

            return $this->redirect('/account');
        }

        $accountId = $this->auth->id();
        $email = $this->auth->user()['email'] ?? null;

        if ($accountId === null || !is_string($email) || $email === '') {
            $this->flash('error', $this->t('auth.no_email'));

            return $this->redirect('/account');
        }

        try {
            $this->emails->sendVerification($accountId, $email);
            $this->flash('success', $this->t('auth.verify_sent'));
        } catch (\RuntimeException) {
            $this->flash('error', $this->t('auth.mail_send_failed'));
        }

        return $this->redirect('/account');
    }
}
