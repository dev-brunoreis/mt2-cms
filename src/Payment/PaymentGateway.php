<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

interface PaymentGateway
{
    public function id(): string;

    public function createCheckout(PaymentIntent $intent): CheckoutRedirect;

    /**
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawBody, array $headers): WebhookEvent;
}
