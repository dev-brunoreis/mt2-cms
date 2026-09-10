<?php

declare(strict_types=1);

namespace Mt2Cms\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): void;

    public function isConfigured(): bool;
}
