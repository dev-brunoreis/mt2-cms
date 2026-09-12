<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFormatDecimal(): void
    {
        self::assertSame('5.00', Money::formatDecimal(500));
        self::assertSame('500.00', Money::formatDecimal(50000));
        self::assertSame('0.01', Money::formatDecimal(1));
        self::assertSame('1.23', Money::formatDecimal(123));
    }

    public function testFormatHonorsSelectedStyle(): void
    {
        self::assertSame('1,234.56', Money::formatDecimal(123456, Money::FORMAT_DOT));
        self::assertSame('1.234,56', Money::formatDecimal(123456, Money::FORMAT_COMMA));
        self::assertSame('1 234,56', Money::formatDecimal(123456, Money::FORMAT_SPACE));
        self::assertSame('1,234.56', Money::formatDecimal(123456, 'unknown'));
    }

    public function testCentsFromWholeUnits(): void
    {
        self::assertSame(50000, Money::centsFromInput('500'));
        self::assertSame(50000, Money::centsFromInput('500.00'));
        self::assertSame(50000, Money::centsFromInput('500,00'));
    }

    public function testCentsFromGroupedInput(): void
    {
        self::assertSame(123456, Money::centsFromInput('1.234,56'));
        self::assertSame(123456, Money::centsFromInput('1,234.56'));
        self::assertSame(50000, Money::centsFromInput(' 500,00 '));
    }

    public function testRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::centsFromInput('abc');
    }

    public function testRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::centsFromInput('0');
    }
}
