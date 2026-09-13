<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Payment;

use Mt2Cms\Payment\PaymentWebhookEnvelope;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookEnvelopeTest extends TestCase
{
    public function testPaypalEventIdUsesWebhookId(): void
    {
        self::assertSame(
            'WH-1',
            PaymentWebhookEnvelope::eventId('paypal', ['id' => 'WH-1'], '{}'),
        );
    }

    public function testMissingEventIdFallsBackToHash(): void
    {
        $raw = '{"x":1}';

        self::assertSame(
            'sha256:' . hash('sha256', $raw),
            PaymentWebhookEnvelope::eventId('paypal', [], $raw),
        );
    }

    public function testMercadoPagoEventIdPrefersTopLevelId(): void
    {
        self::assertSame(
            '999',
            PaymentWebhookEnvelope::eventId('mercadopago', [
                'id' => 999,
                'data' => ['id' => 'pay-1'],
            ], '{}'),
        );
    }

    public function testFilterHeadersKeepsProviderFieldsOnly(): void
    {
        $filtered = PaymentWebhookEnvelope::filterHeaders([
            'PAYPAL-TRANSMISSION-ID' => 'abc',
            'Cookie' => 'MT2CMS=secret',
            'X-Signature' => 'sig',
            'Authorization' => 'Bearer x',
            'content-type' => 'application/json',
        ]);

        self::assertSame([
            'PAYPAL-TRANSMISSION-ID' => 'abc',
            'X-Signature' => 'sig',
            'content-type' => 'application/json',
        ], $filtered);
    }

    public function testPaypalPaymentHintsReadReferenceAndOrder(): void
    {
        $hints = PaymentWebhookEnvelope::paymentHints('paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER-9',
                'purchase_units' => [
                    ['reference_id' => '15'],
                ],
            ],
        ]);

        self::assertSame('ORDER-9', $hints['provider_ref']);
        self::assertSame(15, $hints['payment_id']);
    }
}
