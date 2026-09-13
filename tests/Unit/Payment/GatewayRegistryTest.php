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
        self::assertTrue($registry->has('off'));
        self::assertCount(2, $registry->all());
        self::assertCount(1, $registry->configured());
    }
}
