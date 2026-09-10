<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Repository\AccountEmailRepository;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\EmailTokenRepository;

class AccountEmailService
{
    private const VERIFY_TTL = 86400;
    private const RESET_TTL = 3600;

    public function __construct(
        private AccountRepository $accounts,
        private AccountEmailRepository $accountEmails,
        private EmailTokenRepository $tokens,
        private MailerInterface $mailer,
        private SettingsService $settings,
    ) {
    }

    public function syncFromAccount(int $accountId, string $email, bool $verified = false): void
    {
        $this->accountEmails->upsert($accountId, $email, $verified);
    }

    public function isVerified(int $accountId): bool
    {
        return $this->accountEmails->isVerified($accountId);
    }

    public function sendVerification(int $accountId, string $email): void
    {
        $token = $this->tokens->create($accountId, 'verify', $email, self::VERIFY_TTL);
        $url = $this->absoluteUrl('/verify-email/' . $token['plain']);
        $subject = $this->settings->mailSubjectPrefix() . 'Verify your email';
        $body = "Hello,\n\nPlease verify your email by visiting:\n{$url}\n\nThis link expires in 24 hours.";

        $this->mailer->send($email, $subject, $body);
    }

    public function verify(string $plainToken): bool
    {
        $row = $this->tokens->consume($plainToken, 'verify');

        if ($row === null) {
            return false;
        }

        $accountId = (int) $row['account_id'];
        $email = (string) ($row['email'] ?? '');

        if ($email !== '') {
            $this->accounts->updateEmail($accountId, $email);
            $this->accountEmails->upsert($accountId, $email, true);
        } else {
            $this->accountEmails->markVerified($accountId);
        }

        return true;
    }

    public function requestPasswordReset(string $loginOrEmail): void
    {
        $account = $this->accounts->findForPasswordReset($loginOrEmail);

        if ($account === null) {
            return;
        }

        $accountId = (int) $account['id'];
        $email = (string) ($account['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $token = $this->tokens->create($accountId, 'reset', $email, self::RESET_TTL);
        $url = $this->absoluteUrl('/reset-password/' . $token['plain']);
        $subject = $this->settings->mailSubjectPrefix() . 'Password reset';
        $body = "Hello,\n\nReset your password by visiting:\n{$url}\n\nThis link expires in 1 hour.\n\nIf you did not request this, ignore this email.";

        $this->mailer->send($email, $subject, $body);
    }

    public function resetPassword(string $plainToken, string $newPassword): bool
    {
        $row = $this->tokens->consume($plainToken, 'reset');

        if ($row === null) {
            return false;
        }

        $this->accounts->resetPassword((int) $row['account_id'], $newPassword);

        return true;
    }

    public function requestEmailChange(int $accountId, string $newEmail): void
    {
        $newEmail = trim($newEmail);

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('auth.invalid_email');
        }

        $token = $this->tokens->create($accountId, 'change_email', $newEmail, self::VERIFY_TTL);
        $url = $this->absoluteUrl('/verify-email/' . $token['plain']);
        $subject = $this->settings->mailSubjectPrefix() . 'Confirm email change';
        $body = "Hello,\n\nConfirm your new email by visiting:\n{$url}\n\nThis link expires in 24 hours.";

        $this->mailer->send($newEmail, $subject, $body);
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim($this->settings->siteUrl(), '/');

        return $base . $path;
    }
}
