<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Payment;

use Mt2Cms\Payment\CheckoutUrl;
use PHPUnit\Framework\TestCase;

final class CheckoutUrlTest extends TestCase
{
    public function testAcceptsHttpsCheckoutHost(): void
    {
        self::assertTrue(CheckoutUrl::isSafe('https://www.sandbox.paypal.com/checkoutnow?token=EC-1'));
        self::assertTrue(CheckoutUrl::isSafe('https://www.paypal.com/checkoutnow?token=EC-1'));
    }

    public function testRejectsNonHttpsAndJunk(): void
    {
        self::assertFalse(CheckoutUrl::isSafe(''));
        self::assertFalse(CheckoutUrl::isSafe('http://www.sandbox.paypal.com/checkoutnow'));
        self::assertFalse(CheckoutUrl::isSafe('javascript:alert(1)'));
        self::assertFalse(CheckoutUrl::isSafe("https://www.paypal.com/checkoutnow\r\nLocation: https://evil.test"));
        self::assertFalse(CheckoutUrl::isSafe('https://user:pass@evil.test/'));
        self::assertFalse(CheckoutUrl::isSafe('/donate'));
    }
}
