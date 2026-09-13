<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service\Economy;

use Mt2Cms\Service\Economy\EconomyAnomaly;
use Mt2Cms\Service\Economy\EconomyStats;
use Mt2Cms\Service\Economy\GoldlogHintParser;
use Mt2Cms\Service\Economy\ItemLogHintParser;
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

    public function testResolveVnumIsCaseInsensitiveAndStripsPlusLevel(): void
    {
        $map = [
            'long sword' => 299,
            'yang ore' => 80007,
        ];

        self::assertSame(299, GoldlogHintParser::resolveVnum('Long Sword+9', $map));
        self::assertSame(80007, GoldlogHintParser::resolveVnum('Yang Ore 20', $map));
        self::assertNull(GoldlogHintParser::resolveVnum('Unknown Blade', $map));
    }

    public function testItemLogShopHint(): void
    {
        $parsed = ItemLogHintParser::parseShop('Lunar Sword+9 1([SA]Admin) 40 1');
        self::assertNotNull($parsed);
        self::assertSame('Lunar Sword+9', $parsed['name']);
        self::assertSame(1, $parsed['other_pid']);
        self::assertSame('[SA]Admin', $parsed['other_name']);
        self::assertSame(40, $parsed['yang']);
        self::assertSame(1, $parsed['count']);

        $big = ItemLogHintParser::parseShop('Lunar Sword+9 2(Test) 900000000 1');
        self::assertNotNull($big);
        self::assertSame(900000000, $big['yang']);
        self::assertNull(ItemLogHintParser::parseShop('Lunar Sword+9 2 1'));
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

    public function testZScoreAndWealth(): void
    {
        $z = EconomyStats::zScore(100.0, [10, 12, 11, 13, 10, 12]);
        self::assertNotNull($z);
        self::assertGreaterThan(3.0, $z);

        $alert = EconomyAnomaly::zScoreAlert(100.0, [10, 12, 11, 13, 10, 12]);
        self::assertNotNull($alert);
        self::assertSame(EconomyAnomaly::KIND_ZSCORE, $alert['kind']);

        $conc = EconomyStats::wealthConcentration([100, 50, 25, 25], 200);
        self::assertSame(50.0, $conc['top1_pct']);
        self::assertSame(50.0, $conc['top5_pct']);

        $big = EconomyStats::wealthConcentration(
            array_merge([900], array_fill(0, 99, 1)),
            999,
        );
        self::assertGreaterThan(80.0, $big['top1_pct']);
    }

    public function testVolumeConcentrationVelocity(): void
    {
        $vol = EconomyAnomaly::volumeAlert(30, 5.0);
        self::assertNotNull($vol);
        self::assertSame(EconomyAnomaly::KIND_VOLUME_SPIKE, $vol['kind']);
        self::assertNull(EconomyAnomaly::volumeAlert(4, 5.0));

        $c = EconomyAnomaly::concentrationAlert(0.8, 3, 20);
        self::assertNotNull($c);
        self::assertSame(EconomyAnomaly::KIND_CONCENTRATION, $c['kind']);
        self::assertNull(EconomyAnomaly::concentrationAlert(0.5, 3, 20));

        $v = EconomyAnomaly::yangVelocityAlert(600_000_000, 10, 15);
        self::assertNotNull($v);
        self::assertSame(EconomyAnomaly::KIND_YANG_VELOCITY, $v['kind']);
        self::assertNull(EconomyAnomaly::yangVelocityAlert(1000, 10, 15));
    }
}
