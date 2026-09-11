<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Discord\DiscordWebhookService;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class CashCreditServiceTest extends TestCase
{
    public function testCreditIfPaidReturnsFalseWhenPaymentMissing(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findByProviderRef')->willReturn(null);

        $accounts = $this->createMock(AccountRepository::class);
        $service = new CashCreditService($payments, $accounts, $this->discord());

        self::assertFalse($service->creditIfPaid('paypal', 'ORDER-1'));
    }

    public function testCreditIfPaidReturnsTrueWhenAlreadyCredited(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findByProviderRef')->willReturn([
            'id' => 1,
            'account_id' => 5,
            'cash_amount' => 100,
            'status' => 'paid',
            'credited_at' => '2026-01-01 00:00:00',
        ]);

        $accounts = $this->createMock(AccountRepository::class);
        $service = new CashCreditService($payments, $accounts, $this->discord());

        self::assertTrue($service->creditIfPaid('paypal', 'ORDER-1'));
    }

    public function testMarkPaidAndCreditMarksPendingPayment(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $paidRow = [
            'id' => 2,
            'account_id' => 7,
            'account_login' => 'player1',
            'cash_amount' => 50,
            'status' => 'paid',
            'credited_at' => null,
        ];
        $payments->method('findByProviderRef')->willReturnOnConsecutiveCalls(
            [
                'id' => 2,
                'account_id' => 7,
                'account_login' => 'player1',
                'cash_amount' => 50,
                'status' => 'pending',
                'credited_at' => null,
            ],
            $paidRow,
            $paidRow,
        );
        $payments->expects(self::once())->method('markPaid')->with(2);
        $payments->expects(self::once())->method('markCredited')->with(2);

        $accounts = $this->createMock(AccountRepository::class);
        $accounts->method('acquireNamedLock')->willReturn(true);
        $accounts->method('creditCash')->with(7, 50)->willReturn(true);
        $accounts->method('releaseNamedLock');

        $service = new CashCreditService($payments, $accounts, $this->discord());

        self::assertTrue($service->markPaidAndCredit('paypal', 'ORDER-2'));
    }

    private function discord(): DiscordWebhookService
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('discordWebhookUrl')->willReturn('');
        $settings->method('siteUrl')->willReturn('https://example.test');

        return new DiscordWebhookService($settings);
    }
}
