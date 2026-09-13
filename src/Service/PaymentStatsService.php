<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\Economy\EconomyStats;

class PaymentStatsService
{
    /** @var list<string> */
    public const RANGE_OPTIONS = ['today', '7d', '30d'];

    public function __construct(private PaymentRepository $payments)
    {
    }

    /**
     * @return array{
     *   currency: string,
     *   paid_cents: int,
     *   cash_amount: int,
     *   open_pending: int,
     *   other_currencies: list<array{currency: string, amount_cents: int}>,
     *   spark: array{labels: list<string>, values: list<int>},
     *   spark_from: string,
     *   spark_to: string
     * }
     */
    public function dashboardSnapshot(): array
    {
        $range = self::parseRange('7d');
        $sparkTo = $range['to_day'];
        $sparkFrom = date('Y-m-d', strtotime($sparkTo . ' -13 days') ?: time());
        $sparkBounds = self::datetimeBounds($sparkFrom, $sparkTo);

        $paid = $this->payments->paidTotalsByCurrency($range['from'], $range['to']);
        $currency = self::pickDisplayCurrency($paid);
        $paidRow = self::rowForCurrency($paid, $currency);
        $cash = $this->payments->cashCredited($range['from'], $range['to']);

        $daily = $currency !== ''
            ? $this->payments->dailyPaid($sparkBounds['from'], $sparkBounds['to'], $currency)
            : [];
        $series = self::fillDailySeries($sparkFrom, $sparkTo, $daily, 'amount_cents');

        return [
            'currency' => $currency,
            'paid_cents' => (int) ($paidRow['amount_cents'] ?? 0),
            'cash_amount' => (int) ($cash['cash_amount'] ?? 0),
            'open_pending' => $this->payments->openPendingCount(),
            'other_currencies' => self::otherCurrencies($paid, $currency),
            'spark' => self::seriesFromDays($series),
            'spark_from' => $sparkFrom,
            'spark_to' => $sparkTo,
        ];
    }

    /**
     * @return array{
     *   range: string,
     *   range_options: list<string>,
     *   currency: string,
     *   other_currencies: list<array{currency: string, amount_cents: int}>,
     *   chart_days: int,
     *   chart_from: string,
     *   chart_to: string,
     *   kpis: array{
     *     paid_cents: int,
     *     paid_cents_delta: ?float,
     *     cash_amount: int,
     *     cash_amount_delta: ?float,
     *     paid_count: int,
     *     paid_count_delta: ?float,
     *     pending: int,
     *     pending_delta: ?float,
     *     refunded_cents: int
     *   },
     *   revenue_chart: array{labels: list<string>, values: list<int>},
     *   cash_chart: array{labels: list<string>, values: list<int>}
     * }
     */
    public function paymentsOverview(string $rangeKey): array
    {
        $range = self::parseRange($rangeKey);
        $paid = $this->payments->paidTotalsByCurrency($range['from'], $range['to']);
        $prevPaid = $this->payments->paidTotalsByCurrency($range['prev_from'], $range['prev_to']);
        $currency = self::pickDisplayCurrency($paid);

        if ($currency === '') {
            $currency = self::pickDisplayCurrency($prevPaid);
        }

        $paidRow = self::rowForCurrency($paid, $currency);
        $prevPaidRow = self::rowForCurrency($prevPaid, $currency);
        $cash = $this->payments->cashCredited($range['from'], $range['to']);
        $prevCash = $this->payments->cashCredited($range['prev_from'], $range['prev_to']);
        $status = $this->payments->statusCounts($range['from'], $range['to']);
        $prevStatus = $this->payments->statusCounts($range['prev_from'], $range['prev_to']);

        $paidCents = (int) ($paidRow['amount_cents'] ?? 0);
        $paidCount = (int) ($paidRow['paid_count'] ?? 0);
        $cashAmount = (int) ($cash['cash_amount'] ?? 0);
        $pending = (int) ($status['pending'] ?? 0);
        $refunded = self::centsForCurrency($status['refunded_by_currency'] ?? [], $currency);

        $chartDays = $range['chart_days'];
        $chartTo = $range['to_day'];
        $chartFrom = date('Y-m-d', strtotime($chartTo . ' -' . ($chartDays - 1) . ' days') ?: time());
        $chartBounds = self::datetimeBounds($chartFrom, $chartTo);

        $dailyPaid = $currency !== ''
            ? $this->payments->dailyPaid($chartBounds['from'], $chartBounds['to'], $currency)
            : [];
        $dailyCash = $this->payments->dailyCashCredited($chartBounds['from'], $chartBounds['to']);
        $paidSeries = self::fillDailySeries($chartFrom, $chartTo, $dailyPaid, 'amount_cents');
        $cashSeries = self::fillDailySeries($chartFrom, $chartTo, $dailyCash, 'cash_amount');

        return [
            'range' => $range['key'],
            'range_options' => self::RANGE_OPTIONS,
            'currency' => $currency,
            'other_currencies' => self::otherCurrencies($paid, $currency),
            'chart_days' => $chartDays,
            'chart_from' => $chartFrom,
            'chart_to' => $chartTo,
            'kpis' => [
                'paid_cents' => $paidCents,
                'paid_cents_delta' => EconomyStats::relativeChange(
                    (float) $paidCents,
                    (float) ($prevPaidRow['amount_cents'] ?? 0),
                ),
                'cash_amount' => $cashAmount,
                'cash_amount_delta' => EconomyStats::relativeChange(
                    (float) $cashAmount,
                    (float) ($prevCash['cash_amount'] ?? 0),
                ),
                'paid_count' => $paidCount,
                'paid_count_delta' => EconomyStats::relativeChange(
                    (float) $paidCount,
                    (float) ($prevPaidRow['paid_count'] ?? 0),
                ),
                'pending' => $pending,
                'pending_delta' => EconomyStats::relativeChange(
                    (float) $pending,
                    (float) ($prevStatus['pending'] ?? 0),
                ),
                'refunded_cents' => $refunded,
            ],
            'revenue_chart' => self::seriesFromDays($paidSeries),
            'cash_chart' => self::seriesFromDays($cashSeries),
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   from_day: string,
     *   to_day: string,
     *   from: string,
     *   to: string,
     *   prev_from: string,
     *   prev_to: string,
     *   chart_days: int
     * }
     */
    public static function parseRange(string $raw): array
    {
        $key = in_array($raw, self::RANGE_OPTIONS, true) ? $raw : '7d';
        $toDay = date('Y-m-d');
        $days = match ($key) {
            'today' => 0,
            '30d' => 29,
            default => 6,
        };
        $fromDay = date('Y-m-d', strtotime($toDay . ' -' . $days . ' days') ?: time());
        $span = $days + 1;
        $prevTo = date('Y-m-d', strtotime($fromDay . ' -1 day') ?: time());
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($span - 1) . ' days') ?: time());
        $bounds = self::datetimeBounds($fromDay, $toDay);
        $prevBounds = self::datetimeBounds($prevFrom, $prevTo);

        return [
            'key' => $key,
            'from_day' => $fromDay,
            'to_day' => $toDay,
            'from' => $bounds['from'],
            'to' => $bounds['to'],
            'prev_from' => $prevBounds['from'],
            'prev_to' => $prevBounds['to'],
            'chart_days' => $key === '30d' ? 30 : 7,
        ];
    }

    /**
     * Inclusive calendar days → `[from, to)` datetimes.
     *
     * @return array{from: string, to: string}
     */
    public static function datetimeBounds(string $fromDay, string $toDay): array
    {
        $fromTs = strtotime($fromDay . ' 00:00:00');
        $toTs = strtotime($toDay . ' 00:00:00 +1 day');

        return [
            'from' => date('Y-m-d 00:00:00', $fromTs !== false ? $fromTs : time()),
            'to' => date('Y-m-d 00:00:00', $toTs !== false ? $toTs : time()),
        ];
    }

    /**
     * @param list<array{currency?: string, amount_cents?: int}> $paidByCurrency
     */
    public static function pickDisplayCurrency(array $paidByCurrency): string
    {
        $best = '';
        $bestCents = -1;

        foreach ($paidByCurrency as $row) {
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));

            if ($currency === '' || strlen($currency) !== 3) {
                continue;
            }

            $cents = (int) ($row['amount_cents'] ?? 0);

            if ($cents > $bestCents) {
                $bestCents = $cents;
                $best = $currency;
            }
        }

        return $best;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{day: string, value: int}>
     */
    public static function fillDailySeries(string $fromDay, string $toDay, array $rows, string $valueKey): array
    {
        $byDay = [];

        foreach ($rows as $row) {
            $day = (string) ($row['day'] ?? '');

            if ($day !== '') {
                $byDay[$day] = (int) ($row[$valueKey] ?? 0);
            }
        }

        $cursor = strtotime($fromDay . ' 00:00:00');
        $end = strtotime($toDay . ' 00:00:00');

        if ($cursor === false || $end === false || $cursor > $end) {
            return [];
        }

        $out = [];

        while ($cursor <= $end) {
            $day = date('Y-m-d', $cursor);
            $out[] = [
                'day' => $day,
                'value' => $byDay[$day] ?? 0,
            ];
            $next = strtotime('+1 day', $cursor);

            if ($next === false) {
                break;
            }

            $cursor = $next;
        }

        return $out;
    }

    /**
     * @param list<array{currency?: string, amount_cents?: int}> $paidByCurrency
     * @return list<array{currency: string, amount_cents: int}>
     */
    public static function otherCurrencies(array $paidByCurrency, string $displayCurrency): array
    {
        $others = [];

        foreach ($paidByCurrency as $row) {
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            $cents = (int) ($row['amount_cents'] ?? 0);

            if ($currency === '' || $currency === $displayCurrency || $cents < 1) {
                continue;
            }

            $others[] = [
                'currency' => $currency,
                'amount_cents' => $cents,
            ];
        }

        return $others;
    }

    /**
     * @param list<array{day: string, value: int}> $rows
     * @return array{labels: list<string>, values: list<int>}
     */
    public static function seriesFromDays(array $rows): array
    {
        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = (string) ($row['day'] ?? '');
            $values[] = (int) ($row['value'] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @param list<array{currency?: string, amount_cents?: int}> $rows
     * @return array{currency: string, amount_cents: int, paid_count: int}
     */
    private static function rowForCurrency(array $rows, string $currency): array
    {
        $empty = ['currency' => $currency, 'amount_cents' => 0, 'paid_count' => 0];

        if ($currency === '') {
            return $empty;
        }

        foreach ($rows as $row) {
            if (strtoupper((string) ($row['currency'] ?? '')) === $currency) {
                return [
                    'currency' => $currency,
                    'amount_cents' => (int) ($row['amount_cents'] ?? 0),
                    'paid_count' => (int) ($row['paid_count'] ?? 0),
                ];
            }
        }

        return $empty;
    }

    /**
     * @param list<array{currency?: string, amount_cents?: int}> $rows
     */
    private static function centsForCurrency(array $rows, string $currency): int
    {
        if ($currency === '') {
            return 0;
        }

        foreach ($rows as $row) {
            if (strtoupper((string) ($row['currency'] ?? '')) === $currency) {
                return (int) ($row['amount_cents'] ?? 0);
            }
        }

        return 0;
    }
}
