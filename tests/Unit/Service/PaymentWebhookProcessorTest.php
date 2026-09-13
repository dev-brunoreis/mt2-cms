<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PaymentGateway;
use Mt2Cms\Payment\WebhookEvent;
use Mt2Cms\Repository\PaymentEventRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\PaymentWebhookProcessor;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookProcessorTest extends TestCase
{
    public function testIngestRejectsInvalidSignatureWithoutAttaching(): void
    {
        $events = $this->createMock(PaymentEventRepository::class);
        $events->method('insertOrGet')->willReturn([
            'row' => $this->eventRow(),
            'created' => true,
        ]);
        $events->expects(self::once())->method('markRejected')->with(3, 'payments.invalid_webhook_signature');
        $events->method('findById')->willReturn($this->eventRow(['status' => 'rejected']));
        $events->expects(self::never())->method('attachPayment');

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('parseWebhook')->willThrowException(
            new \InvalidArgumentException('payments.invalid_webhook_signature'),
        );

        $payments = $this->createMock(PaymentRepository::class);
        $payments->expects(self::never())->method('findById');

        $processor = $this->processor($events, $payments, $gateway);
        $result = $processor->ingest('paypal', '{"id":"WH-1","event_type":"CHECKOUT.ORDER.APPROVED"}', []);

        self::assertFalse($result['accepted']);
        self::assertSame('rejected', $result['row']['status']);
    }

    public function testIngestSkipsAlreadyProcessedDuplicate(): void
    {
        $events = $this->createMock(PaymentEventRepository::class);
        $events->method('insertOrGet')->willReturn([
            'row' => $this->eventRow(['status' => 'processed']),
            'created' => false,
        ]);

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->expects(self::never())->method('parseWebhook');

        $processor = $this->processor($events, $this->createMock(PaymentRepository::class), $gateway);
        $result = $processor->ingest('paypal', '{"id":"WH-1"}', []);

        self::assertTrue($result['accepted']);
        self::assertFalse($result['created']);
    }

    public function testProcessOneCreditsWhenGatewayReportsPaid(): void
    {
        $row = $this->eventRow([
            'raw_body' => json_encode([
                'id' => 'WH-1',
                'event_type' => 'CHECKOUT.ORDER.APPROVED',
                'resource' => [
                    'id' => 'ORDER-1',
                    'purchase_units' => [['reference_id' => '15']],
                ],
            ], JSON_THROW_ON_ERROR),
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
        ]);
        $events = $this->createMock(PaymentEventRepository::class);
        $events->method('findById')->willReturn($row);
        $events->expects(self::once())->method('markProcessed')->with(3);
        $events->expects(self::once())->method('attachPayment')->with(3, 15);

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('processWebhook')->willReturn(
            new WebhookEvent('ORDER-1', 'CHECKOUT.ORDER.APPROVED', true),
        );

        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findById')->with(15)->willReturn(['id' => 15]);

        $credits = $this->createMock(CashCreditService::class);
        $credits->expects(self::once())->method('markPaidAndCredit')->with('paypal', 'ORDER-1');

        $processor = $this->processor($events, $payments, $gateway, $credits);

        self::assertTrue($processor->processOne(3));
    }

    public function testProcessOneRetriesWhenCaptureFails(): void
    {
        $row = $this->eventRow([
            'raw_body' => json_encode([
                'id' => 'WH-1',
                'event_type' => 'CHECKOUT.ORDER.APPROVED',
                'resource' => ['id' => 'ORDER-1'],
            ], JSON_THROW_ON_ERROR),
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'attempts' => 0,
        ]);
        $events = $this->createMock(PaymentEventRepository::class);
        $events->method('findById')->willReturn($row);
        $events->expects(self::once())->method('markRetry');
        $events->expects(self::never())->method('markProcessed');

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('processWebhook')->willThrowException(
            new \RuntimeException('payments.paypal_request_failed'),
        );

        $credits = $this->createMock(CashCreditService::class);
        $credits->expects(self::never())->method('markPaidAndCredit');

        $processor = $this->processor(
            $events,
            $this->createMock(PaymentRepository::class),
            $gateway,
            $credits,
        );

        self::assertFalse($processor->processOne(3));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function eventRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 3,
            'payment_id' => null,
            'provider' => 'paypal',
            'provider_event_id' => 'WH-1',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'headers_json' => '{}',
            'raw_body' => '{"id":"WH-1"}',
            'status' => 'queued',
            'attempts' => 0,
            'available_at' => '2026-09-13 00:00:00',
            'last_error' => null,
            'processed_at' => null,
            'created_at' => '2026-09-13 00:00:00',
        ], $overrides);
    }

    private function processor(
        PaymentEventRepository $events,
        PaymentRepository $payments,
        PaymentGateway $gateway,
        ?CashCreditService $credits = null,
    ): PaymentWebhookProcessor {
        $registry = new GatewayRegistry();
        $gateway->method('id')->willReturn('paypal');
        $registry->register($gateway);

        return new PaymentWebhookProcessor(
            $events,
            $payments,
            $registry,
            $credits ?? $this->createMock(CashCreditService::class),
            sys_get_temp_dir(),
        );
    }
}
