<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

interface PaymentGateway
{
    public function id(): string;

    public function labelKey(): string;

    public function configured(): bool;

    public function enabled(): bool;

    public function pendingMinutes(): int;

    public function currency(): string;

    public function createCheckout(PaymentIntent $intent): CheckoutRedirect;

    /**
     * Capture/verify after browser return. Null when the gateway is webhook-only for completion.
     *
     * @param array<string, string> $query
     */
    public function captureReturn(array $query): ?WebhookEvent;

    /**
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawBody, array $headers): WebhookEvent;
}
