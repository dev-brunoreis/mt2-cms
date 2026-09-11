<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

/**
 * Pure PayPal webhook payload helpers (testable without HTTP).
 */
final class PayPalWebhookParser
{
    /**
     * @param array<string, mixed> $resource
     */
    public static function resolveOrderId(string $eventType, array $resource): string
    {
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $supplementary = is_array($resource['supplementary_data'] ?? null)
                ? $resource['supplementary_data']
                : [];
            $related = is_array($supplementary['related_ids'] ?? null)
                ? $supplementary['related_ids']
                : [];
            $orderId = (string) ($related['order_id'] ?? '');

            if ($orderId === '') {
                throw new \InvalidArgumentException('payments.invalid_webhook');
            }

            return $orderId;
        }

        if (str_starts_with($eventType, 'CHECKOUT.ORDER.')) {
            $orderId = (string) ($resource['id'] ?? '');

            if ($orderId === '') {
                throw new \InvalidArgumentException('payments.invalid_webhook');
            }

            return $orderId;
        }

        throw new \InvalidArgumentException('payments.unsupported_webhook_event');
    }

    public static function isPaymentEvent(string $eventType): bool
    {
        return in_array($eventType, [
            'CHECKOUT.ORDER.APPROVED',
            'CHECKOUT.ORDER.COMPLETED',
            'PAYMENT.CAPTURE.COMPLETED',
        ], true);
    }

    public static function canVerifySignature(string $webhookId): bool
    {
        return trim($webhookId) !== '';
    }
}
