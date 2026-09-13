<?php

declare(strict_types=1);

namespace Mt2Cms\Service\Economy;

/**
 * Decide supply / price / volume / concentration alerts from snapshot deltas.
 */
final class EconomyAnomaly
{
    public const KIND_SUPPLY_UP = 'supply_up';
    public const KIND_SUPPLY_DOWN = 'supply_down';
    public const KIND_PRICE_CRASH = 'price_crash';
    public const KIND_PRICE_SPIKE = 'price_spike';
    public const KIND_ZSCORE = 'price_zscore';
    public const KIND_VOLUME_SPIKE = 'volume_spike';
    public const KIND_CONCENTRATION = 'concentration';
    public const KIND_YANG_VELOCITY = 'yang_velocity';

    public const DEFAULT_SUPPLY_PCT = 40.0;
    public const DEFAULT_PRICE_WATCH_PCT = 30.0;
    public const DEFAULT_PRICE_RADAR_PCT = 50.0;
    public const MIN_SUPPLY_UNITS = 20;
    public const MIN_TRADES_WATCH = 3;
    public const MIN_TRADES_RADAR = 8;
    public const ZSCORE_THRESHOLD = 3.0;
    public const VOLUME_SPIKE_RATIO = 3.0;
    public const CONCENTRATION_SHARE = 0.70;
    public const CONCENTRATION_MIN_TRADES = 10;
    public const YANG_VELOCITY_MIN = 500_000_000;
    public const YANG_VELOCITY_MIN_SOURCES = 8;
    public const YANG_VELOCITY_MAX_MINUTES = 30;

    /**
     * @return array{kind: string, change: float}|null
     */
    public static function supplyAlert(
        int $unitsNow,
        int $unitsBaseline,
        float $thresholdPct = self::DEFAULT_SUPPLY_PCT,
        int $minUnits = self::MIN_SUPPLY_UNITS,
    ): ?array {
        if ($unitsNow < $minUnits && $unitsBaseline < $minUnits) {
            return null;
        }

        $change = EconomyStats::relativeChange((float) $unitsNow, (float) $unitsBaseline);

        if (!EconomyStats::crossesThreshold($change, $thresholdPct) || $change === null) {
            return null;
        }

        return [
            'kind' => $change > 0 ? self::KIND_SUPPLY_UP : self::KIND_SUPPLY_DOWN,
            'change' => $change,
        ];
    }

    /**
     * @return array{kind: string, change: float}|null
     */
    public static function priceAlert(
        ?float $medianNow,
        ?float $medianBaseline,
        int $tradesNow,
        bool $watched,
        ?float $crashPct = null,
        ?float $spikePct = null,
        ?int $minSample = null,
    ): ?array {
        if ($medianNow === null || $medianBaseline === null || $medianBaseline <= 0) {
            return null;
        }

        $minTrades = $minSample ?? ($watched ? self::MIN_TRADES_WATCH : self::MIN_TRADES_RADAR);

        if ($tradesNow < $minTrades) {
            return null;
        }

        $downPct = $crashPct ?? ($watched ? self::DEFAULT_PRICE_WATCH_PCT : self::DEFAULT_PRICE_RADAR_PCT);
        $upPct = $spikePct ?? ($watched ? self::DEFAULT_PRICE_WATCH_PCT : self::DEFAULT_PRICE_RADAR_PCT);

        $change = EconomyStats::relativeChange($medianNow, $medianBaseline);

        if ($change === null) {
            return null;
        }

        if ($change < 0 && EconomyStats::crossesThreshold($change, $downPct)) {
            return ['kind' => self::KIND_PRICE_CRASH, 'change' => $change];
        }

        if ($change > 0 && EconomyStats::crossesThreshold($change, $upPct)) {
            return ['kind' => self::KIND_PRICE_SPIKE, 'change' => $change];
        }

        return null;
    }

    /**
     * @param list<int|float> $historyMedians
     * @return array{kind: string, change: float, z_score: float}|null
     */
    public static function zScoreAlert(
        ?float $medianNow,
        array $historyMedians,
        float $threshold = self::ZSCORE_THRESHOLD,
    ): ?array {
        if ($medianNow === null || count($historyMedians) < 5) {
            return null;
        }

        $z = EconomyStats::zScore($medianNow, $historyMedians);

        if ($z === null || abs($z) < $threshold) {
            return null;
        }

        $mean = EconomyStats::mean($historyMedians) ?? 0.0;
        $change = EconomyStats::relativeChange($medianNow, $mean) ?? 0.0;

        return [
            'kind' => self::KIND_ZSCORE,
            'change' => $change,
            'z_score' => $z,
        ];
    }

    /**
     * @return array{kind: string, change: float}|null
     */
    public static function volumeAlert(
        int $tradesNow,
        ?float $avgDailyTrades,
        float $ratio = self::VOLUME_SPIKE_RATIO,
    ): ?array {
        if ($avgDailyTrades === null || $avgDailyTrades < 1.0 || $tradesNow < 5) {
            return null;
        }

        if ($tradesNow < $avgDailyTrades * $ratio) {
            return null;
        }

        return [
            'kind' => self::KIND_VOLUME_SPIKE,
            'change' => ($tradesNow / $avgDailyTrades) - 1.0,
        ];
    }

    /**
     * @return array{kind: string, change: float}|null
     */
    public static function concentrationAlert(
        float $topShare,
        int $playerCount,
        int $trades,
        float $shareThreshold = self::CONCENTRATION_SHARE,
        int $minTrades = self::CONCENTRATION_MIN_TRADES,
        int $maxPlayers = 4,
    ): ?array {
        if ($trades < $minTrades || $playerCount < 1 || $playerCount > $maxPlayers) {
            return null;
        }

        if ($topShare < $shareThreshold) {
            return null;
        }

        return [
            'kind' => self::KIND_CONCENTRATION,
            'change' => $topShare,
        ];
    }

    /**
     * @return array{kind: string, change: float}|null
     */
    public static function yangVelocityAlert(
        int $yangReceived,
        int $uniqueSources,
        int $windowMinutes,
        int $minYang = self::YANG_VELOCITY_MIN,
        int $minSources = self::YANG_VELOCITY_MIN_SOURCES,
        int $maxMinutes = self::YANG_VELOCITY_MAX_MINUTES,
    ): ?array {
        if ($yangReceived < $minYang || $uniqueSources < $minSources || $windowMinutes > $maxMinutes) {
            return null;
        }

        return [
            'kind' => self::KIND_YANG_VELOCITY,
            'change' => (float) $yangReceived,
        ];
    }

    /**
     * Human drop hint from supply × price signals (never auto-applied).
     */
    public static function dropAdvice(?float $supplyChange, ?float $priceChange): string
    {
        $supplyUp = $supplyChange !== null && $supplyChange >= 0.15;
        $supplyDown = $supplyChange !== null && $supplyChange <= -0.15;
        $priceDown = $priceChange !== null && $priceChange <= -0.15;
        $priceUp = $priceChange !== null && $priceChange >= 0.15;

        if ($supplyUp && $priceDown) {
            return 'consider_lower_drop';
        }

        if ($supplyDown && $priceUp) {
            return 'consider_raise_drop';
        }

        if ($supplyDown && $priceDown) {
            return 'demand_died';
        }

        if ($priceChange === null && $supplyUp) {
            return 'supply_up_no_price';
        }

        if ($priceChange === null && $supplyDown) {
            return 'supply_down_no_price';
        }

        return 'stable';
    }
}
