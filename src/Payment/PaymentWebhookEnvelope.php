<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

/**
 * Pure helpers for inbound webhook envelopes (testable without HTTP).
 */
final class PaymentWebhookEnvelope
{
    public const MAX_BODY_BYTES = 262144;

    /**
     * @param array<string, mixed> $payload
     */
    public static function eventId(string $provider, array $payload, string $rawBody): string
    {
        if ($provider === 'paypal') {
            $id = trim((string) ($payload['id'] ?? ''));

            return $id !== '' ? $id : 'sha256:' . hash('sha256', $rawBody);
        }

        $id = trim((string) ($payload['id'] ?? ''));

        if ($id !== '') {
            return $id;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $dataId = trim((string) ($data['id'] ?? ''));

        if ($dataId !== '') {
            $type = trim((string) ($payload['type'] ?? $payload['action'] ?? 'event'));

            return $type . ':' . $dataId;
        }

        return 'sha256:' . hash('sha256', $rawBody);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function eventType(string $provider, array $payload): string
    {
        if ($provider === 'paypal') {
            return trim((string) ($payload['event_type'] ?? ''));
        }

        return trim((string) ($payload['type'] ?? $payload['action'] ?? ''));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public static function filterHeaders(array $headers): array
    {
        $keepExact = [
            'correlation-id' => true,
            'content-type' => true,
            'user-agent' => true,
            'x-signature' => true,
            'x-request-id' => true,
            'accept' => true,
        ];
        $filtered = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower($name);

            if (str_starts_with($lower, 'paypal-') || isset($keepExact[$lower])) {
                $filtered[$name] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{provider_ref: string, payment_id: int}
     */
    public static function paymentHints(string $provider, array $payload): array
    {
        if ($provider === 'paypal') {
            $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
            $eventType = self::eventType($provider, $payload);
            $providerRef = '';

            try {
                $providerRef = PayPalWebhookParser::resolveOrderId($eventType, $resource);
            } catch (\InvalidArgumentException) {
                $providerRef = trim((string) ($resource['id'] ?? ''));
            }

            $units = is_array($resource['purchase_units'] ?? null) ? $resource['purchase_units'] : [];
            $first = is_array($units[0] ?? null) ? $units[0] : [];
            $paymentId = (int) ($first['reference_id'] ?? 0);

            return ['provider_ref' => $providerRef, 'payment_id' => $paymentId];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'provider_ref' => trim((string) ($payload['external_reference'] ?? $data['id'] ?? '')),
            'payment_id' => (int) ($payload['external_reference'] ?? 0),
        ];
    }
}
