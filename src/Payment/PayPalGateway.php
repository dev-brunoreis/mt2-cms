<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

use Mt2Cms\Service\SettingsService;
use Mt2Cms\Support\Log;

final class PayPalGateway implements PaymentGateway
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function id(): string
    {
        return 'paypal';
    }

    public function createCheckout(PaymentIntent $intent): CheckoutRedirect
    {
        $token = $this->accessToken();
        $base = $this->apiBase();
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $intent->paymentId,
                'amount' => [
                    'currency_code' => $intent->currency,
                    'value' => $this->formatAmount($intent->amountCents),
                ],
                'description' => 'Cash package #' . $intent->packageId,
            ]],
            'application_context' => [
                'return_url' => $intent->returnUrl,
                'cancel_url' => $intent->cancelUrl,
                'brand_name' => $this->settings->mailFromName(),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->request('POST', $base . '/v2/checkout/orders', $token, $body);
        $orderId = (string) ($response['id'] ?? '');

        if ($orderId === '') {
            throw new \RuntimeException('payments.paypal_order_failed');
        }

        $approvalUrl = '';

        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approvalUrl = (string) ($link['href'] ?? '');
                break;
            }
        }

        if ($approvalUrl === '') {
            throw new \RuntimeException('payments.paypal_order_failed');
        }

        return new CheckoutRedirect($orderId, $approvalUrl);
    }

    public function captureOrder(string $orderId): bool
    {
        $token = $this->accessToken();
        $base = $this->apiBase();
        $response = $this->request('POST', $base . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $token, []);

        $status = (string) ($response['status'] ?? '');

        return in_array($status, ['COMPLETED', 'APPROVED'], true);
    }

    /**
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawBody, array $headers): WebhookEvent
    {
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('payments.invalid_webhook');
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $providerRef = (string) ($resource['id'] ?? '');

        if ($providerRef === '') {
            throw new \InvalidArgumentException('payments.invalid_webhook');
        }

        $paid = in_array($eventType, [
            'CHECKOUT.ORDER.APPROVED',
            'CHECKOUT.ORDER.COMPLETED',
            'PAYMENT.CAPTURE.COMPLETED',
        ], true);

        if ($paid && !$this->verifyOrderPaid($providerRef)) {
            $paid = false;
        }

        return new WebhookEvent($providerRef, $eventType, $paid);
    }

    private function verifyOrderPaid(string $orderId): bool
    {
        try {
            $token = $this->accessToken();
            $base = $this->apiBase();
            $response = $this->request('GET', $base . '/v2/checkout/orders/' . rawurlencode($orderId), $token, null);
            $status = (string) ($response['status'] ?? '');

            return in_array($status, ['COMPLETED', 'APPROVED'], true);
        } catch (\Throwable $e) {
            Log::error('payments', 'PayPal order verify failed', $e);

            return false;
        }
    }

    private function accessToken(): string
    {
        $clientId = $this->settings->paypalClientId();
        $secret = $this->settings->paypalClientSecret();

        if ($clientId === '' || $secret === '') {
            throw new \RuntimeException('payments.paypal_not_configured');
        }

        $base = $this->apiBase();
        $ch = curl_init($base . '/v1/oauth2/token');

        if ($ch === false) {
            throw new \RuntimeException('payments.paypal_request_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $clientId . ':' . $secret,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $code < 200 || $code >= 300) {
            throw new \RuntimeException('payments.paypal_auth_failed');
        }

        $data = json_decode($raw, true);
        $token = is_array($data) ? (string) ($data['access_token'] ?? '') : '';

        if ($token === '') {
            throw new \RuntimeException('payments.paypal_auth_failed');
        }

        return $token;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, string $token, ?array $body): array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('payments.paypal_request_failed');
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body === null ? '{}' : json_encode($body, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw)) {
            throw new \RuntimeException('payments.paypal_request_failed');
        }

        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300 || !is_array($data)) {
            throw new \RuntimeException('payments.paypal_request_failed');
        }

        return $data;
    }

    private function apiBase(): string
    {
        return $this->settings->paypalMode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
