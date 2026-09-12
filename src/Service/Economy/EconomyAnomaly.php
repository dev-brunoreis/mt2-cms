<?php

declare(strict_types=1);

namespace Mt2Cms\Service\Economy;

/**
 * Decide supply / price alerts from snapshot deltas.
 */
final class EconomyAnomaly
{
    public const KIND_SUPPLY_UP = 'supply_up';
    public const KIND_SUPPLY_DOWN = 'supply_down';
    public const KIND_PRICE_CRASH = 'price_crash';
    public const KIND_PRICE_SPIKE = 'price_spike';

    public const DEFAULT_SUPPLY_PCT = 40.0;
    public const DEFAULT_PRICE_WATCH_PCT = 30.0;
    public const DEFAULT_PRICE_RADAR_PCT = 50.0;
    public const MIN_SUPPLY_UNITS = 20;
    public const MIN_TRADES_WATCH = 3;
    public const MIN_TRADES_RADAR = 8;

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
