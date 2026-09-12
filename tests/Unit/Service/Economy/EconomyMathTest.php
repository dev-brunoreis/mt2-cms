<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service\Economy;

use Mt2Cms\Service\Economy\EconomyAnomaly;
use Mt2Cms\Service\Economy\EconomyStats;
use Mt2Cms\Service\Economy\GoldlogHintParser;
use PHPUnit\Framework\TestCase;

final class EconomyMathTest extends TestCase
{
    public function testMedianOddAndEven(): void
    {
        self::assertSame(2.0, EconomyStats::median([1, 2, 3]));
        self::assertSame(2.5, EconomyStats::median([1, 2, 3, 4]));
        self::assertNull(EconomyStats::median([]));
    }

    public function testPercentile(): void
    {
        $p25 = EconomyStats::percentile([10, 20, 30, 40], 25);
        self::assertNotNull($p25);
        self::assertEqualsWithDelta(17.5, $p25, 0.01);
    }

    public function testRelativeChangeAndThreshold(): void
    {
        self::assertEqualsWithDelta(-0.5, EconomyStats::relativeChange(50, 100), 0.0001);
        self::assertTrue(EconomyStats::crossesThreshold(-0.5, 40));
        self::assertFalse(EconomyStats::crossesThreshold(-0.2, 40));
        self::assertNull(EconomyStats::relativeChange(10, 0));
    }

    public function testGoldlogHintParser(): void
    {
        self::assertSame(['name' => 'Yang Ore', 'count' => 20], GoldlogHintParser::parse('Yang Ore 20'));
        self::assertSame(['name' => 'Sword+9', 'count' => 1], GoldlogHintParser::parse('Sword+9'));
        self::assertNull(GoldlogHintParser::parse(''));
        self::assertSame(50, GoldlogHintParser::unitPrice(1000, 20));
        self::assertNull(GoldlogHintParser::unitPrice(0, 1));
        self::assertSame(40, strlen(GoldlogHintParser::tradeKey('2026-01-01', '12:00:00', 1, 100, 'x')));
    }

    public function testSupplyAlert(): void
    {
        $up = EconomyAnomaly::supplyAlert(200, 100, 40, 20);
        self::assertNotNull($up);
        self::assertSame(EconomyAnomaly::KIND_SUPPLY_UP, $up['kind']);

        $noise = EconomyAnomaly::supplyAlert(2, 1, 40, 20);
        self::assertNull($noise);
    }

    public function testPriceAlertWatchVsRadar(): void
    {
        $watch = EconomyAnomaly::priceAlert(70.0, 100.0, 3, true);
        self::assertNotNull($watch);
        self::assertSame(EconomyAnomaly::KIND_PRICE_CRASH, $watch['kind']);

        $radarTooFew = EconomyAnomaly::priceAlert(70.0, 100.0, 3, false);
        self::assertNull($radarTooFew);

        $radar = EconomyAnomaly::priceAlert(40.0, 100.0, 8, false);
        self::assertNotNull($radar);
        self::assertSame(EconomyAnomaly::KIND_PRICE_CRASH, $radar['kind']);
    }

    public function testDropAdvice(): void
    {
        self::assertSame('consider_lower_drop', EconomyAnomaly::dropAdvice(0.4, -0.3));
        self::assertSame('consider_raise_drop', EconomyAnomaly::dropAdvice(-0.4, 0.3));
        self::assertSame('demand_died', EconomyAnomaly::dropAdvice(-0.4, -0.3));
        self::assertSame('supply_up_no_price', EconomyAnomaly::dropAdvice(0.4, null));
        self::assertSame('stable', EconomyAnomaly::dropAdvice(0.05, 0.05));
    }
}
