<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

use Mt2Cms\Service\SettingsService;
use Mt2Cms\Support\Log;

/**
 * Mercado Pago Checkout Pro (+ PIX as a payment method on the preference).
 * Configure access token + webhook secret under Settings → Payment methods.
 */
final class MercadoPagoGateway implements PaymentGateway
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function id(): string
    {
        return 'mercadopago';
    }

    public function labelKey(): string
    {
        return 'admin.payment_methods.mercadopago';
    }

    public function configured(): bool
    {
        return $this->settings->mercadoPagoConfigured();
    }

    public function pendingMinutes(): int
    {
        return $this->settings->mercadoPagoPendingMinutes();
    }

    public function currency(): string
    {
        return $this->settings->mercadoPagoCurrency();
    }

    public function createCheckout(PaymentIntent $intent): CheckoutRedirect
    {
        $token = $this->settings->mercadoPagoAccessToken();

        if ($token === '') {
            throw new \RuntimeException('payments.mercadopago_not_configured');
        }

        $body = [
            'external_reference' => (string) $intent->paymentId,
            'items' => [[
                'id' => (string) $intent->packageId,
                'title' => 'Cash package #' . $intent->packageId,
                'quantity' => 1,
                'currency_id' => $intent->currency,
                'unit_price' => round($intent->amountCents / 100, 2),
            ]],
            'payment_methods' => [
                'default_payment_method_id' => 'pix',
                'excluded_payment_types' => [],
            ],
            'back_urls' => [
                'success' => $intent->returnUrl,
                'pending' => $intent->returnUrl,
                'failure' => $intent->cancelUrl,
            ],
            'auto_return' => 'approved',
            'notification_url' => rtrim($this->settings->siteUrl(), '/') . '/payments/webhook/mercadopago',
        ];

        $response = $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $token, $body);
        $preferenceId = (string) ($response['id'] ?? '');
        $initPoint = (string) ($response['init_point'] ?? $response['sandbox_init_point'] ?? '');

        if ($preferenceId === '' || $initPoint === '') {
            throw new \RuntimeException('payments.mercadopago_preference_failed');
        }

        return new CheckoutRedirect($preferenceId, $initPoint);
    }

    /**
     * @param array<string, string> $query
     */
    public function captureReturn(array $query): ?WebhookEvent
    {
        // Completion is webhook-driven; return URL only acknowledges the browser flow.
        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    public function parseWebhook(string $rawBody, array $headers): WebhookEvent
    {
        if (!$this->configured()) {
            throw new \InvalidArgumentException('payments.mercadopago_not_configured');
        }

        $secret = $this->settings->mercadoPagoWebhookSecret();

        if ($secret === '') {
            throw new \InvalidArgumentException('payments.invalid_webhook_signature');
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('payments.invalid_webhook');
        }

        $signature = $headers['X-SIGNATURE'] ?? $headers['X-Signature'] ?? '';
        $requestId = $headers['X-REQUEST-ID'] ?? $headers['X-Request-Id'] ?? '';

        if (!$this->verifySignature($rawBody, (string) $signature, (string) $requestId, $secret, $payload)) {
            throw new \InvalidArgumentException('payments.invalid_webhook_signature');
        }

        $type = (string) ($payload['type'] ?? $payload['action'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $paymentId = (string) ($data['id'] ?? $payload['data.id'] ?? '');

        if ($paymentId === '') {
            throw new \InvalidArgumentException('payments.invalid_webhook');
        }

        $paid = str_contains(strtolower($type), 'payment') || str_contains(strtolower($type), 'updated');
        $status = 'PENDING';

        if ($paid) {
            $detail = $this->fetchPayment($paymentId);
            $status = strtoupper((string) ($detail['status'] ?? 'PENDING'));
            $paid = $status === 'APPROVED';
            $ref = (string) ($detail['preference_id'] ?? '');

            if ($ref === '') {
                $ref = (string) ($detail['order']['id'] ?? $paymentId);
            }

            return new WebhookEvent($ref, $status, $paid);
        }

        return new WebhookEvent($paymentId, $status, false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verifySignature(
        string $rawBody,
        string $signatureHeader,
        string $requestId,
        string $secret,
        array $payload,
    ): bool {
        if ($signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $chunk) {
            $kv = explode('=', trim($chunk), 2);

            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        if ($ts === '' || $v1 === '') {
            return false;
        }

        $dataId = (string) ($payload['data']['id'] ?? '');
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPayment(string $paymentId): array
    {
        $token = $this->settings->mercadoPagoAccessToken();
        $response = $this->request(
            'GET',
            'https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId),
            $token,
            null,
        );

        return $response;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, string $token, ?array $body): array
    {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('payments.mercadopago_http_failed');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            Log::error('payments', 'MercadoPago HTTP ' . $status . ': ' . (is_string($raw) ? $raw : ''));

            throw new \RuntimeException('payments.mercadopago_http_failed');
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('payments.mercadopago_http_failed');
        }

        return $decoded;
    }
}
