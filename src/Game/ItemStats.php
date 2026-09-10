<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

use Mt2Cms\Game\Proto\ProtoEnums;

class ItemStats
{
    /** @var list<string> */
    private const WEARABLE_TYPES = ['ITEM_WEAPON', 'ITEM_ARMOR', 'ITEM_COSTUME'];

    /** @var list<array{0: string, 1: list<string>}> job locale key, anti-flag tokens */
    private const JOB_ANTI = [
        ['0', ['ANTI_MUSA', 'ANTI_WARRIOR']],
        ['1', ['ANTI_ASSASSIN']],
        ['2', ['ANTI_SURA']],
        ['3', ['ANTI_MUDANG', 'ANTI_SHAMAN']],
        ['8', ['ANTI_WOLFMAN', 'ANTI_LYCAN']],
    ];

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
     *   bonuses: list<array{token: string, value: int, percent: bool, rare: bool}>,
     *   special_title: bool,
     *   wearable: array{jobs: list<string>, sex: string|null}|null
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
        int|string $antiflag = 0,
    ): array {
        $values = array_pad(array_map(static fn (mixed $value): int => (int) $value, array_slice($values, 0, 6)), 6, 0);
        $typeName = $this->enums->itemTypeName($type);
        $subtypeName = $this->enums->itemSubtypeName($typeName, $subtype);
        $bonuses = $this->namedApplies($attrs, true);

        return [
            'stats' => $this->baseStats($typeName, $subtypeName, $limitType, $limitValue, $values),
            'applies' => $this->namedApplies($applies),
            'bonuses' => $bonuses,
            'special_title' => $bonuses !== [],
            'wearable' => $this->wearable($typeName, $antiflag),
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
            $physical = $this->weaponPower('damage', $values[3], $values[4], $values[5]);
            $magic = $this->weaponPower('magic_attack', $values[1], $values[2], $values[5], true);

            if ($subtype === 'WEAPON_FAN') {
                if ($magic !== null) {
                    $stats[] = $magic;
                }
                if ($physical !== null) {
                    $stats[] = $physical;
                }
            } else {
                if ($physical !== null) {
                    $stats[] = $physical;
                }
                if ($magic !== null) {
                    $stats[] = $magic;
                }
            }
        }

        if ($type === 'ITEM_ARMOR' || ($type === 'ITEM_COSTUME' && $subtype === 'COSTUME_BODY')) {
            $defGrade = $values[1];
            if ($defGrade > 0) {
                $stats[] = ['key' => 'defence', 'value' => $defGrade + ($values[5] * 2)];
            }

            if ($values[0] > 0) {
                $stats[] = ['key' => 'magic_defence', 'value' => $values[0]];
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function weaponPower(string $key, int $min, int $max, int $add, bool $optional = false): ?array
    {
        $min += $add;
        $max += $add;

        if ($optional && $min <= 0 && $max <= 0) {
            return null;
        }

        if ($max > $min) {
            return ['key' => $key, 'min' => $min, 'max' => $max];
        }

        return ['key' => $key, 'value' => $min];
    }

    /**
     * @param list<array{type: int, value: int}> $slots
     * @return list<array{token: string, value: int, percent: bool, rare?: bool}>
     */
    private function namedApplies(array $slots, bool $markRare = false): array
    {
        $out = [];

        foreach ($slots as $index => $slot) {
            $token = $this->enums->applyTypeName((int) ($slot['type'] ?? 0));
            $value = (int) ($slot['value'] ?? 0);

            if ($token === '' || $token === 'APPLY_NONE' || $value === 0) {
                continue;
            }

            $row = [
                'token' => $token,
                'value' => $value,
                'percent' => $this->isPercent($token),
            ];

            if ($markRare) {
                $row['rare'] = (int) $index >= 5;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array{jobs: list<string>, sex: string|null}|null
     */
    private function wearable(string $type, int|string $antiflag): ?array
    {
        if (!in_array($type, self::WEARABLE_TYPES, true)) {
            return null;
        }

        $flagSet = array_fill_keys($this->enums->flagsFromValue($antiflag, 'antiflag'), true);
        $known = array_fill_keys($this->enums->bitmaskTokens('antiflag'), true);
        $jobs = [];

        foreach (self::JOB_ANTI as [$job, $antiTokens]) {
            $schemaHasJob = false;
            $blocked = false;

            foreach ($antiTokens as $token) {
                if (isset($known[$token])) {
                    $schemaHasJob = true;
                }
                if (isset($flagSet[$token])) {
                    $blocked = true;
                }
            }

            if (!$schemaHasJob && $job === '8') {
                continue;
            }

            if (!$blocked) {
                $jobs[] = $job;
            }
        }

        $sex = null;
        if (isset($flagSet['ANTI_MALE'])) {
            $sex = 'female';
        } elseif (isset($flagSet['ANTI_FEMALE'])) {
            $sex = 'male';
        }

        return ['jobs' => $jobs, 'sex' => $sex];
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
