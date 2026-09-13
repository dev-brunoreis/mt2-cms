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

    public function labelKey(): string
    {
        return 'admin.payment_methods.paypal';
    }

    public function configured(): bool
    {
        return $this->settings->paypalConfigured();
    }

    public function enabled(): bool
    {
        return $this->settings->paypalEnabled();
    }

    public function pendingMinutes(): int
    {
        return $this->settings->paypalPendingMinutes();
    }

    public function currency(): string
    {
        return $this->settings->paypalCurrency();
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

    /**
     * @param array<string, string> $query
     */
    public function captureReturn(array $query): ?WebhookEvent
    {
        $token = trim((string) ($query['token'] ?? ''));

        if ($token === '') {
            return null;
        }

        $paid = $this->captureOrder($token);

        return new WebhookEvent($token, $paid ? 'COMPLETED' : 'PENDING', $paid);
    }

    public function captureOrder(string $orderId): bool
    {
        $token = $this->accessToken();
        $base = $this->apiBase();
        $response = $this->request('POST', $base . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $token, []);

        return (string) ($response['status'] ?? '') === 'COMPLETED';
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

        if (!$this->verifyWebhookSignature($rawBody, $headers, $payload)) {
            throw new \InvalidArgumentException('payments.invalid_webhook_signature');
        }

        $eventType = (string) ($payload['event_type'] ?? '');
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $orderId = PayPalWebhookParser::resolveOrderId($eventType, $resource);

        $paid = false;

        if (PayPalWebhookParser::isPaymentEvent($eventType)) {
            if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
                try {
                    $this->captureOrder($orderId);
                } catch (\Throwable $e) {
                    Log::error('payments', 'PayPal capture on APPROVED failed for ' . $orderId, $e);
                }
            }

            $paid = $this->verifyOrderCompleted($orderId);
        }

        return new WebhookEvent($orderId, $eventType, $paid);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function verifyWebhookSignature(string $rawBody, array $headers, array $payload): bool
    {
        $webhookId = $this->settings->paypalWebhookId();

        if (!PayPalWebhookParser::canVerifySignature($webhookId)) {
            Log::error('payments', 'PayPal webhook ID is not configured');

            return false;
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $transmissionId = $normalized['PAYPAL_TRANSMISSION_ID'] ?? '';
        $transmissionTime = $normalized['PAYPAL_TRANSMISSION_TIME'] ?? '';
        $certUrl = $normalized['PAYPAL_CERT_URL'] ?? '';
        $authAlgo = $normalized['PAYPAL_AUTH_ALGO'] ?? '';
        $transmissionSig = $normalized['PAYPAL_TRANSMISSION_SIG'] ?? '';

        if ($transmissionId === '' || $transmissionSig === '') {
            Log::error('payments', 'PayPal webhook missing transmission headers');

            return false;
        }

        try {
            $token = $this->accessToken();
            $base = $this->apiBase();
            $body = [
                'auth_algo' => $authAlgo,
                'cert_url' => $certUrl,
                'transmission_id' => $transmissionId,
                'transmission_sig' => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ];
            $response = $this->request('POST', $base . '/v1/notifications/verify-webhook-signature', $token, $body);
            $status = (string) ($response['verification_status'] ?? '');

            return strtoupper($status) === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('payments', 'PayPal webhook signature verify failed', $e);

            return false;
        }
    }

    private function verifyOrderCompleted(string $orderId): bool
    {
        try {
            $token = $this->accessToken();
            $base = $this->apiBase();
            $response = $this->request('GET', $base . '/v2/checkout/orders/' . rawurlencode($orderId), $token, null);
            $status = (string) ($response['status'] ?? '');

            return $status === 'COMPLETED';
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
