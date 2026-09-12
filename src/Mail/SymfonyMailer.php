<?php

declare(strict_types=1);

namespace Mt2Cms\Mail;

use Mt2Cms\Support\Env;
use Mt2Cms\Support\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

final class SymfonyMailer implements MailerInterface
{
    private ?Mailer $mailer = null;

    public function __construct(
        private string $fromAddress,
        private string $fromName,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->resolveDsn() !== null;
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): void
    {
        $dsn = $this->resolveDsn();

        if ($dsn === null) {
            throw new \RuntimeException('mail.not_configured');
        }

        if ($this->mailer === null) {
            $this->mailer = new Mailer(Transport::fromDsn($dsn));
        }

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to($to)
            ->subject($subject)
            ->text($textBody);

        if ($htmlBody !== null && $htmlBody !== '') {
            $email->html($htmlBody);
        }

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            Log::error('mail', 'Failed to send email', $e);

            throw new \RuntimeException('mail.send_failed');
        }
    }

    private function resolveDsn(): ?string
    {
        $env = Env::getInstance();
        $dsn = trim((string) ($env->get('MAIL_DSN') ?? ''));

        if ($dsn !== '') {
            return $dsn;
        }

        $host = trim((string) ($env->get('MAIL_HOST') ?? ''));

        if ($host === '') {
            return null;
        }

        $port = (int) ($env->get('MAIL_PORT') ?? 587);
        $user = rawurlencode((string) ($env->get('MAIL_USER') ?? ''));
        $pass = rawurlencode((string) ($env->get('MAIL_PASSWORD') ?? ''));
        $scheme = strtolower((string) ($env->get('MAIL_SCHEME') ?? 'smtp'));

        if ($user !== '' && $pass !== '') {
            return sprintf('%s://%s:%s@%s:%d', $scheme, $user, $pass, $host, $port);
        }

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }
}
