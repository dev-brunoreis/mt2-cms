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
}
