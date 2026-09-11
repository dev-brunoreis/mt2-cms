<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Payment;

use Mt2Cms\Payment\PayPalWebhookParser;
use PHPUnit\Framework\TestCase;

final class PayPalWebhookParserTest extends TestCase
{
    public function testResolveOrderIdFromCheckoutOrderEvent(): void
    {
        self::assertSame(
            'ORDER-123',
            PayPalWebhookParser::resolveOrderId('CHECKOUT.ORDER.COMPLETED', ['id' => 'ORDER-123']),
        );
    }

    public function testResolveOrderIdFromCaptureEvent(): void
    {
        self::assertSame(
            'ORDER-456',
            PayPalWebhookParser::resolveOrderId('PAYMENT.CAPTURE.COMPLETED', [
                'id' => 'CAPTURE-789',
                'supplementary_data' => [
                    'related_ids' => ['order_id' => 'ORDER-456'],
                ],
            ]),
        );
    }

    public function testCaptureEventWithoutOrderIdFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PayPalWebhookParser::resolveOrderId('PAYMENT.CAPTURE.COMPLETED', ['id' => 'CAPTURE-1']);
    }

    public function testUnsupportedEventFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PayPalWebhookParser::resolveOrderId('BILLING.SUBSCRIPTION.CREATED', ['id' => 'X']);
    }

    public function testCanVerifySignatureRequiresWebhookId(): void
    {
        self::assertFalse(PayPalWebhookParser::canVerifySignature(''));
        self::assertFalse(PayPalWebhookParser::canVerifySignature('   '));
        self::assertTrue(PayPalWebhookParser::canVerifySignature('WH-123'));
    }
}
