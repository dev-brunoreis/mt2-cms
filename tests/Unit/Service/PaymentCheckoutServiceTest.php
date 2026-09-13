<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Payment\CheckoutRedirect;
use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PaymentGateway;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\NotificationService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Service\PaymentExpiryService;
use Mt2Cms\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class PaymentCheckoutServiceTest extends TestCase
{
    public function testStartCheckoutMarksFailedAndNotifiesWhenGatewayThrows(): void
    {
        $packages = $this->createMock(CashPackageRepository::class);
        $packages->method('findEnabledById')->willReturn([
            'id' => 3,
            'price_cents' => 999,
            'currency' => 'USD',
            'cash_amount' => 100,
        ]);

        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('createPending')->willReturn([
            'id' => 11,
            'cash_amount' => 100,
        ]);
        $payments->expects(self::once())->method('markFailed')->with(11)->willReturn(true);
        $payments->expects(self::never())->method('updateProviderRef');

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('id')->willReturn('paypal');
        $gateway->method('configured')->willReturn(true);
        $gateway->method('currency')->willReturn('USD');
        $gateway->method('createCheckout')->willThrowException(new \RuntimeException('payments.paypal_auth_failed'));

        $registry = new GatewayRegistry();
        $registry->register($gateway);

        $settings = $this->createMock(SettingsService::class);
        $settings->method('siteUrl')->willReturn('https://example.test');

        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::once())->method('paymentFailed')->with(5, 100, 11);

        $expiry = $this->createMock(PaymentExpiryService::class);
        $expiry->expects(self::once())->method('expireDue');

        $service = new PaymentCheckoutService(
            $packages,
            $payments,
            $registry,
            $settings,
            $notifications,
            $expiry,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('payments.paypal_auth_failed');
        $service->startCheckout(5, 'player1', 3);
    }

    public function testStartCheckoutUpdatesProviderRefOnSuccess(): void
    {
        $packages = $this->createMock(CashPackageRepository::class);
        $packages->method('findEnabledById')->willReturn([
            'id' => 3,
            'price_cents' => 999,
            'currency' => 'USD',
            'cash_amount' => 100,
        ]);

        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('createPending')->willReturn(['id' => 11]);
        $payments->expects(self::once())->method('updateProviderRef')->with(11, 'ORDER-99');
        $payments->expects(self::never())->method('markFailed');

        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('id')->willReturn('paypal');
        $gateway->method('configured')->willReturn(true);
        $gateway->method('currency')->willReturn('USD');
        $gateway->method('createCheckout')->willReturn(new CheckoutRedirect('ORDER-99', 'https://paypal.test/approve'));

        $registry = new GatewayRegistry();
        $registry->register($gateway);

        $settings = $this->createMock(SettingsService::class);
        $settings->method('siteUrl')->willReturn('https://example.test');

        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::never())->method('paymentFailed');

        $expiry = $this->createMock(PaymentExpiryService::class);

        $service = new PaymentCheckoutService(
            $packages,
            $payments,
            $registry,
            $settings,
            $notifications,
            $expiry,
        );

        $result = $service->startCheckout(5, 'player1', 3);

        self::assertSame('https://paypal.test/approve', $result['approval_url']);
        self::assertSame(11, $result['payment_id']);
    }

    public function testCancelPendingMarksFailedAndNotifies(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $payments->method('findByIdForAccount')->willReturn([
            'id' => 11,
            'status' => 'pending',
        ]);
        $payments->expects(self::once())->method('markFailed')->with(11)->willReturn(true);
        $payments->method('findById')->willReturn([
            'id' => 11,
            'cash_amount' => 50,
        ]);

        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::once())->method('paymentCancelled')->with(5, 50, 11);

        $service = new PaymentCheckoutService(
            $this->createMock(CashPackageRepository::class),
            $payments,
            new GatewayRegistry(),
            $this->createMock(SettingsService::class),
            $notifications,
            $this->createMock(PaymentExpiryService::class),
        );

        self::assertTrue($service->cancelPending(11, 5));
    }
}
