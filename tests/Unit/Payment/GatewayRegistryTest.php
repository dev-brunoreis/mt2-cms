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
        $off = $this->createMock(PaymentGateway::class);
        $off->method('id')->willReturn('off');
        $off->method('configured')->willReturn(false);

        $on = $this->createMock(PaymentGateway::class);
        $on->method('id')->willReturn('on');
        $on->method('configured')->willReturn(true);

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
    }

    public function testResolvePicksConfiguredGatewayById(): void
    {
        $paypal = $this->createMock(PaymentGateway::class);
        $paypal->method('id')->willReturn('paypal');
        $paypal->method('configured')->willReturn(true);

        $mp = $this->createMock(PaymentGateway::class);
        $mp->method('id')->willReturn('mercadopago');
        $mp->method('configured')->willReturn(true);

        $registry = new GatewayRegistry();
        $registry->register($paypal);
        $registry->register($mp);

        self::assertSame($paypal, $registry->active());
        self::assertSame($mp, $registry->resolve('mercadopago'));
    }
}
