<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

use Mt2Cms\Game\Proto\ProtoEnums;

class ItemStats
{
    public function __construct(private ProtoEnums $enums)
    {
    }

    /**
     * @param list<int> $values proto value0–value5
     * @param list<array{type: int, value: int}> $applies proto apply slots
     * @param list<array{type: int, value: int}> $attrs item attrtype/attrvalue
     * @return array{
     *   stats: list<array<string, mixed>>,
     *   applies: list<array{token: string, value: int, percent: bool}>,
     *   bonuses: list<array{token: string, value: int, percent: bool}>
     * }
     */
    public function describe(
        int $type,
        int $subtype,
        int $limitType,
        int $limitValue,
        array $values,
        array $applies,
        array $attrs,
    ): array {
        $values = array_pad(array_map(static fn (mixed $value): int => (int) $value, array_slice($values, 0, 6)), 6, 0);
        $typeName = $this->enums->itemTypeName($type);
        $subtypeName = $this->enums->itemSubtypeName($typeName, $subtype);

        return [
            'stats' => $this->baseStats($typeName, $subtypeName, $limitType, $limitValue, $values),
            'applies' => $this->applyEntries($applies),
            'bonuses' => $this->applyEntries($attrs),
        ];
    }

    /**
     * @param list<array{type: int, value: int}> $slots
     * @return list<array{token: string, value: int, percent: bool}>
     */
    public function applyEntries(array $slots): array
    {
        return $this->namedApplies($slots);
    }

    /**
     * @param list<int> $values
     * @return list<array<string, mixed>>
     */
    private function baseStats(string $type, string $subtype, int $limitType, int $limitValue, array $values): array
    {
        $stats = [];
        $limitName = $this->enums->limitTypeName($limitType);

        if ($limitName === 'LEVEL' && $limitValue > 0) {
            $stats[] = ['key' => 'level', 'value' => $limitValue];
        }

        if ($type === 'ITEM_WEAPON') {
            if ($values[1] > 0 || $values[2] > 0) {
                $stats[] = ['key' => 'damage', 'min' => $values[1], 'max' => $values[2]];
            }

            if ($values[4] > 0) {
                $stats[] = ['key' => 'magic_attack', 'value' => $values[4]];
            }
        }

        if ($type === 'ITEM_ARMOR' || ($type === 'ITEM_COSTUME' && $subtype === 'COSTUME_BODY')) {
            if ($values[1] > 0) {
                $stats[] = ['key' => 'defence', 'value' => $values[1]];
            }
        }

        return $stats;
    }

    /**
     * @param list<array{type: int, value: int}> $slots
     * @return list<array{token: string, value: int, percent: bool}>
     */
    private function namedApplies(array $slots): array
    {
        $out = [];

        foreach ($slots as $slot) {
            $token = $this->enums->applyTypeName((int) ($slot['type'] ?? 0));
            $value = (int) ($slot['value'] ?? 0);

            if ($token === '' || $token === 'APPLY_NONE' || $value === 0) {
                continue;
            }

            $out[] = [
                'token' => $token,
                'value' => $value,
                'percent' => $this->isPercent($token),
            ];
        }

        return $out;
    }

    private function isPercent(string $token): bool
    {
        foreach (['_PCT', '_SPEED', '_REGEN', 'ATTBONUS', 'RESIST', 'MALL_', 'STEAL', 'BLOCK', 'DODGE', 'REFLECT'] as $needle) {
            if (str_contains($token, $needle)) {
                return true;
            }
        }

        return false;
    }
}
