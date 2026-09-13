<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\PaymentStatsService;
use PHPUnit\Framework\TestCase;

final class PaymentStatsServiceTest extends TestCase
{
    public function testPickDisplayCurrencyUsesHighestPaidVolume(): void
    {
        self::assertSame('EUR', PaymentStatsService::pickDisplayCurrency([
            ['currency' => 'usd', 'amount_cents' => 400],
            ['currency' => 'EUR', 'amount_cents' => 900],
            ['currency' => 'BRL', 'amount_cents' => 100],
        ]));
    }

    public function testPickDisplayCurrencySkipsInvalidCodes(): void
    {
        self::assertSame('USD', PaymentStatsService::pickDisplayCurrency([
            ['currency' => 'US', 'amount_cents' => 9999],
            ['currency' => 'USD', 'amount_cents' => 1],
        ]));
        self::assertSame('', PaymentStatsService::pickDisplayCurrency([]));
    }

    public function testFillDailySeriesInsertsZeroDays(): void
    {
        $filled = PaymentStatsService::fillDailySeries(
            '2026-09-01',
            '2026-09-04',
            [
                ['day' => '2026-09-01', 'amount_cents' => 250],
                ['day' => '2026-09-04', 'amount_cents' => 100],
            ],
            'amount_cents',
        );

        self::assertSame([
            ['day' => '2026-09-01', 'value' => 250],
            ['day' => '2026-09-02', 'value' => 0],
            ['day' => '2026-09-03', 'value' => 0],
            ['day' => '2026-09-04', 'value' => 100],
        ], $filled);
    }

    public function testDatetimeBoundsAreHalfOpen(): void
    {
        self::assertSame(
            ['from' => '2026-09-01 00:00:00', 'to' => '2026-09-08 00:00:00'],
            PaymentStatsService::datetimeBounds('2026-09-01', '2026-09-07'),
        );
    }

    public function testParseRangeFallsBackToSevenDays(): void
    {
        $range = PaymentStatsService::parseRange('nope');

        self::assertSame('7d', $range['key']);
        self::assertSame(7, $range['chart_days']);
        self::assertSame($range['from'], PaymentStatsService::datetimeBounds($range['from_day'], $range['to_day'])['from']);
        self::assertSame($range['to'], PaymentStatsService::datetimeBounds($range['from_day'], $range['to_day'])['to']);
    }

    public function testOtherCurrenciesOmitsDisplayAndZeros(): void
    {
        self::assertSame(
            [['currency' => 'EUR', 'amount_cents' => 200]],
            PaymentStatsService::otherCurrencies([
                ['currency' => 'USD', 'amount_cents' => 800],
                ['currency' => 'EUR', 'amount_cents' => 200],
                ['currency' => 'BRL', 'amount_cents' => 0],
            ], 'USD'),
        );
    }

    public function testSeriesFromDaysKeepsOrder(): void
    {
        self::assertSame(
            [
                'labels' => ['2026-09-01', '2026-09-02'],
                'values' => [100, 250],
            ],
            PaymentStatsService::seriesFromDays([
                ['day' => '2026-09-01', 'value' => 100],
                ['day' => '2026-09-02', 'value' => 250],
            ]),
        );
    }

    public function testPaymentsOverviewUsesDisplayCurrencyAndDeltas(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $range = PaymentStatsService::parseRange('7d');

        $payments->method('paidTotalsByCurrency')->willReturnCallback(
            static function (string $from) use ($range): array {
                if ($from === $range['from']) {
                    return [
                        ['currency' => 'USD', 'amount_cents' => 2000, 'paid_count' => 4],
                        ['currency' => 'EUR', 'amount_cents' => 300, 'paid_count' => 1],
                    ];
                }

                return [
                    ['currency' => 'USD', 'amount_cents' => 1000, 'paid_count' => 2],
                ];
            },
        );
        $payments->method('cashCredited')->willReturnCallback(
            static function (string $from) use ($range): array {
                return $from === $range['from']
                    ? ['cash_amount' => 150, 'count' => 3]
                    : ['cash_amount' => 100, 'count' => 2];
            },
        );
        $payments->method('statusCounts')->willReturnCallback(
            static function (string $from) use ($range): array {
                return $from === $range['from']
                    ? [
                        'pending' => 2,
                        'failed' => 0,
                        'expired' => 0,
                        'refunded' => 1,
                        'refunded_by_currency' => [['currency' => 'USD', 'amount_cents' => 500]],
                    ]
                    : [
                        'pending' => 1,
                        'failed' => 0,
                        'expired' => 0,
                        'refunded' => 0,
                        'refunded_by_currency' => [],
                    ];
            },
        );
        $payments->method('dailyPaid')->willReturn([
            ['day' => $range['to_day'], 'amount_cents' => 2000],
        ]);
        $payments->method('dailyCashCredited')->willReturn([
            ['day' => $range['to_day'], 'cash_amount' => 150],
        ]);

        $overview = (new PaymentStatsService($payments))->paymentsOverview('7d');

        self::assertSame('USD', $overview['currency']);
        self::assertSame(2000, $overview['kpis']['paid_cents']);
        self::assertSame(4, $overview['kpis']['paid_count']);
        self::assertSame(150, $overview['kpis']['cash_amount']);
        self::assertSame(2, $overview['kpis']['pending']);
        self::assertSame(500, $overview['kpis']['refunded_cents']);
        self::assertEqualsWithDelta(1.0, $overview['kpis']['paid_cents_delta'], 0.0001);
        self::assertEqualsWithDelta(0.5, $overview['kpis']['cash_amount_delta'], 0.0001);
        self::assertEqualsWithDelta(1.0, $overview['kpis']['paid_count_delta'], 0.0001);
        self::assertEqualsWithDelta(1.0, $overview['kpis']['pending_delta'], 0.0001);
        self::assertSame([['currency' => 'EUR', 'amount_cents' => 300]], $overview['other_currencies']);
        self::assertCount(7, $overview['revenue_chart']['labels']);
        self::assertCount(7, $overview['revenue_chart']['values']);
        self::assertSame(7, $overview['chart_days']);
    }

    public function testDashboardSnapshotDoesNotQueryWhenBuiltFromMocks(): void
    {
        $payments = $this->createMock(PaymentRepository::class);
        $range = PaymentStatsService::parseRange('7d');

        $payments->expects(self::once())->method('paidTotalsByCurrency')
            ->with($range['from'], $range['to'])
            ->willReturn([['currency' => 'USD', 'amount_cents' => 800, 'paid_count' => 2]]);
        $payments->expects(self::once())->method('cashCredited')
            ->with($range['from'], $range['to'])
            ->willReturn(['cash_amount' => 40, 'count' => 2]);
        $payments->expects(self::once())->method('openPendingCount')->willReturn(3);
        $payments->expects(self::once())->method('dailyPaid')->willReturn([]);
        $payments->expects(self::never())->method('statusCounts');

        $snapshot = (new PaymentStatsService($payments))->dashboardSnapshot();

        self::assertSame('USD', $snapshot['currency']);
        self::assertSame(800, $snapshot['paid_cents']);
        self::assertSame(40, $snapshot['cash_amount']);
        self::assertSame(3, $snapshot['open_pending']);
        self::assertSame([], $snapshot['other_currencies']);
        self::assertCount(14, PaymentStatsService::fillDailySeries(
            $snapshot['spark_from'],
            $snapshot['spark_to'],
            [],
            'amount_cents',
        ));
    }
}
