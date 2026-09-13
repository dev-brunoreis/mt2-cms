<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Payment;

use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PaymentGateway;
use PHPUnit\Framework\TestCase;

final class GatewayRegistryTest extends TestCase
{
    public function testGetUnknownThrows(): void
    {
        $registry = new GatewayRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('missing');
    }

    public function testActiveReturnsFirstConfigured(): void
    {
        $off = $this->gateway('off', configured: false, enabled: true);
        $on = $this->gateway('on', configured: true, enabled: true);

        $registry = new GatewayRegistry();
        $registry->register($off);
        $registry->register($on);

        self::assertSame($on, $registry->active());
        self::assertSame($on, $registry->resolve(null));
        self::assertSame($on, $registry->resolve('on'));
        self::assertNull($registry->resolve('off'));
        self::assertNull($registry->resolve('missing'));
        self::assertTrue($registry->has('off'));
        self::assertCount(2, $registry->all());
        self::assertCount(1, $registry->configured());
        self::assertCount(1, $registry->available());
    }

    public function testResolvePicksConfiguredGatewayById(): void
    {
        $paypal = $this->gateway('paypal', configured: true, enabled: true);
        $other = $this->gateway('other', configured: true, enabled: true);

        $registry = new GatewayRegistry();
        $registry->register($paypal);
        $registry->register($other);

        self::assertSame($paypal, $registry->active());
        self::assertSame($other, $registry->resolve('other'));
        self::assertCount(2, $registry->available());
    }

    public function testDisabledGatewayIsConfiguredButNotAvailable(): void
    {
        $paypal = $this->gateway('paypal', configured: true, enabled: false);
        $other = $this->gateway('other', configured: true, enabled: true);

        $registry = new GatewayRegistry();
        $registry->register($paypal);
        $registry->register($other);

        self::assertCount(2, $registry->configured());
        self::assertCount(1, $registry->available());
        self::assertSame($other, $registry->active());
        self::assertNull($registry->resolve('paypal'));
        self::assertSame($other, $registry->resolve('other'));
        self::assertSame($other, $registry->resolve(null));
    }

    private function gateway(string $id, bool $configured, bool $enabled): PaymentGateway
    {
        $gateway = $this->createMock(PaymentGateway::class);
        $gateway->method('id')->willReturn($id);
        $gateway->method('configured')->willReturn($configured);
        $gateway->method('enabled')->willReturn($enabled);

        return $gateway;
    }
}
