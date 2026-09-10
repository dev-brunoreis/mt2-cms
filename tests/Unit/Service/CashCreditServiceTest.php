<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\CashCreditService;
use PHPUnit\Framework\TestCase;

final class CashCreditServiceTest extends TestCase
{
    public function testCreditIfPaidReturnsFalseWhenPaymentMissing(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findByProviderRef')->willReturn(null);

        $accounts = $this->createMock(AccountRepository::class);
        $service = new CashCreditService($payments, $accounts);

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
        $service = new CashCreditService($payments, $accounts);

        self::assertTrue($service->creditIfPaid('paypal', 'ORDER-1'));
    }
}
