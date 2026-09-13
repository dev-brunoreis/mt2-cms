<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\DayChart;
use PHPUnit\Framework\TestCase;

final class DayChartTest extends TestCase
{
    public function testEmptyWhenFewerThanTwoPoints(): void
    {
        $empty = ['points' => '', 'area' => '', 'min_label' => '', 'max_label' => ''];

        self::assertSame($empty, DayChart::fromValues([]));
        self::assertSame($empty, DayChart::fromValues([10]));
    }

    public function testPlotsRisingSeries(): void
    {
        $chart = DayChart::fromValues([0, 100], 100, 40);

        self::assertSame('4,32 96,8', $chart['points']);
        self::assertSame('4,32 96,8 96,38 4,38', $chart['area']);
        self::assertSame('0', $chart['min_label']);
        self::assertSame('100', $chart['max_label']);
    }

    public function testFlatSeriesStillDrawsALine(): void
    {
        $chart = DayChart::fromValues([5, 5], 100, 40);

        self::assertSame('4,32 96,32', $chart['points']);
        self::assertNotSame('', $chart['area']);
        self::assertSame('5', $chart['min_label']);
        self::assertSame('5', $chart['max_label']);
    }
}
