<?php

declare(strict_types=1);

namespace Mt2Cms\Service\Economy;

/**
 * Pure helpers for median / anomaly math (no DB).
 */
final class EconomyStats
{
    /**
     * @param list<int|float> $values
     */
    public static function median(array $values): ?float
    {
        $n = count($values);

        if ($n < 1) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $mid = intdiv($n, 2);

        if ($n % 2 === 1) {
            return (float) $values[$mid];
        }

        return ((float) $values[$mid - 1] + (float) $values[$mid]) / 2.0;
    }

    /**
     * @param list<int|float> $values
     */
    public static function percentile(array $values, float $p): ?float
    {
        $n = count($values);

        if ($n < 1) {
            return null;
        }

        $p = max(0.0, min(100.0, $p));
        sort($values, SORT_NUMERIC);

        if ($n === 1) {
            return (float) $values[0];
        }

        $rank = ($p / 100.0) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        $w = $rank - $lo;

        return ((float) $values[$lo] * (1.0 - $w)) + ((float) $values[$hi] * $w);
    }

    /**
     * Relative change: (now / baseline) - 1. Null if baseline is zero.
     */
    public static function relativeChange(float $now, float $baseline): ?float
    {
        if ($baseline == 0.0) {
            return null;
        }

        return ($now / $baseline) - 1.0;
    }

    public static function crossesThreshold(?float $change, float $thresholdPct): bool
    {
        if ($change === null) {
            return false;
        }

        return abs($change) * 100.0 >= $thresholdPct;
    }

    /**
     * Population standard deviation (sample size n). Null if fewer than 2 values.
     *
     * @param list<int|float> $values
     */
    public static function stdDev(array $values): ?float
    {
        $n = count($values);

        if ($n < 2) {
            return null;
        }

        $mean = array_sum($values) / $n;
        $sumSq = 0.0;

        foreach ($values as $v) {
            $d = (float) $v - $mean;
            $sumSq += $d * $d;
        }

        return sqrt($sumSq / $n);
    }

    /**
     * @param list<int|float> $values
     */
    public static function mean(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Z-score of $value against $values. Null if stddev is zero or sample too small.
     *
     * @param list<int|float> $values
     */
    public static function zScore(float $value, array $values): ?float
    {
        $mean = self::mean($values);
        $sd = self::stdDev($values);

        if ($mean === null || $sd === null || $sd == 0.0) {
            return null;
        }

        return ($value - $mean) / $sd;
    }

    /**
     * Share of total held by top 1% / 5% / 10% of a descending yang list.
     *
     * @param list<int> $yangDescending
     * @return array{top1_pct: float, top5_pct: float, top10_pct: float}
     */
    public static function wealthConcentration(array $yangDescending, int $totalYang): array
    {
        $empty = ['top1_pct' => 0.0, 'top5_pct' => 0.0, 'top10_pct' => 0.0];

        if ($totalYang < 1 || $yangDescending === []) {
            return $empty;
        }

        $n = count($yangDescending);
        $sumTop = static function (int $count) use ($yangDescending): int {
            $sum = 0;
            $limit = min($count, count($yangDescending));

            for ($i = 0; $i < $limit; $i++) {
                $sum += (int) $yangDescending[$i];
            }

            return $sum;
        };

        $top1 = max(1, (int) ceil($n * 0.01));
        $top5 = max(1, (int) ceil($n * 0.05));
        $top10 = max(1, (int) ceil($n * 0.10));

        return [
            'top1_pct' => round(($sumTop($top1) / $totalYang) * 100.0, 2),
            'top5_pct' => round(($sumTop($top5) / $totalYang) * 100.0, 2),
            'top10_pct' => round(($sumTop($top10) / $totalYang) * 100.0, 2),
        ];
    }
}
