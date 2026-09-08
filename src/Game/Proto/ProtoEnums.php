<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

use Mt2Cms\Game\GameProfile;

class ProtoEnums
{
    public const KIND_ITEM = GameProfile::KIND_ITEM;
    public const KIND_MOB = GameProfile::KIND_MOB;

    public function __construct(private GameProfile $profile)
    {
    }

    /**
     * @return list<string>
     */
    public function itemTypes(): array
    {
        return $this->profile->enumList(self::KIND_ITEM, 'types');
    }

    /**
     * @return array<string, list<string>>
     */
    public function itemSubtypesByType(): array
    {
        return $this->profile->subtypesByType();
    }

    /**
     * @return list<string>
     */
    public function subtypesForItemType(string $type): array
    {
        return $this->profile->subtypesByType()[$type] ?? [];
    }

    /**
     * @return list<string>
     */
    public function optionsForField(string $kind, string $fieldKey): array
    {
        if ($kind === self::KIND_ITEM) {
            return match ($fieldKey) {
                'type' => $this->profile->enumList(self::KIND_ITEM, 'types'),
                'limit_type0', 'limit_type1' => $this->profile->enumList(self::KIND_ITEM, 'limit_types'),
                'apply_type0', 'apply_type1', 'apply_type2' => $this->profile->enumList(self::KIND_ITEM, 'apply_types'),
                default => [],
            };
        }

        return match ($fieldKey) {
            'rank' => $this->profile->enumList(self::KIND_MOB, 'ranks'),
            'type' => $this->profile->enumList(self::KIND_MOB, 'types'),
            'battle_type' => $this->profile->enumList(self::KIND_MOB, 'battle_types'),
            'size' => $this->profile->enumList(self::KIND_MOB, 'sizes'),
            default => [],
        };
    }

    public function widgetForField(string $kind, string $fieldKey): string
    {
        if (isset($this->profile->bitmaskFields($kind)[$fieldKey])) {
            return 'bitmask';
        }

        if ($kind === self::KIND_ITEM && $fieldKey === 'subtype') {
            return 'subtype';
        }

        if ($this->optionsForField($kind, $fieldKey) !== []) {
            return 'select';
        }

        if (in_array($fieldKey, ['vnum', 'size', 'level', 'gold', 'shop_buy_price', 'refine', 'refine_set',
            'magic_pct', 'limit_value0', 'limit_value1', 'apply_value0', 'apply_value1', 'apply_value2',
            'value0', 'value1', 'value2', 'value3', 'value4', 'value5', 'specular', 'socket', 'addon_type',
            'mount_capacity', 'empire', 'on_click', 'st', 'dx', 'ht', 'iq', 'damage_min', 'damage_max',
            'max_hp', 'regen_cycle', 'regen_percent', 'gold_min', 'gold_max', 'exp', 'def', 'attack_speed',
            'move_speed', 'aggressive_hp_pct', 'aggressive_sight', 'attack_range', 'drop_item',
            'resurrection_vnum', 'enchant_curse', 'enchant_slow', 'enchant_poison', 'enchant_stun',
            'enchant_critical', 'enchant_penetrate', 'resist_sword', 'resist_twohand', 'resist_dagger',
            'resist_bell', 'resist_fan', 'resist_bow', 'resist_fire', 'resist_elect', 'resist_magic',
            'resist_wind', 'resist_poison', 'summon', 'drain_sp', 'mob_color', 'polymorph_item',
            'skill_level0', 'skill_vnum0', 'skill_level1', 'skill_vnum1', 'skill_level2', 'skill_vnum2',
            'skill_level3', 'skill_vnum3', 'skill_level4', 'skill_vnum4', 'sp_berserk', 'sp_stoneskin',
            'sp_godspeed', 'sp_deathblow', 'sp_revive', 'dam_multiply'], true)) {
            return 'number';
        }

        return 'text';
    }

    /**
     * @return list<string>
     */
    public function parseBitmask(string $value, string $fieldKey, string $kind = self::KIND_ITEM): array
    {
        $value = trim($value);

        if ($value === '' || strcasecmp($value, 'NONE') === 0) {
            return [];
        }

        $fields = $this->profile->bitmaskFields($kind);
        $separator = $fields[$fieldKey]['separator'] ?? '|';
        $parts = array_map('trim', explode($separator, $value));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    public function joinBitmask(array $tokens, string $fieldKey, string $kind = self::KIND_ITEM): string
    {
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn (string $t): bool => $t !== ''));

        if ($tokens === []) {
            return in_array($fieldKey, ['ai_flag', 'race_flag', 'immune_flag'], true) ? '' : 'NONE';
        }

        $fields = $this->profile->bitmaskFields($kind);
        $separator = $fields[$fieldKey]['separator'] ?? '|';

        return implode($separator, $tokens);
    }

    /**
     * @return list<string>
     */
    public function bitmaskTokens(string $fieldKey, string $kind = self::KIND_ITEM): array
    {
        return $this->profile->bitmaskFields($kind)[$fieldKey]['tokens'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function valueLabelsFor(string $itemType, string $subtype = ''): array
    {
        return $this->profile->valueLabelsFor($itemType, $subtype);
    }

    public function equipSlotHint(string $itemType, string $subtype, string $wear): ?string
    {
        if ($itemType === 'ITEM_COSTUME') {
            return match ($subtype) {
                'COSTUME_BODY' => 'hint_costume_body',
                'COSTUME_HAIR' => 'hint_costume_hair',
                default => null,
            };
        }

        if ($itemType === 'ITEM_RING') {
            return 'hint_ring';
        }

        if ($itemType === 'ITEM_BELT') {
            return 'hint_belt';
        }

        if ($itemType === 'ITEM_DS' || $itemType === 'ITEM_SPECIAL_DS') {
            return 'hint_dragon_soul';
        }

        $flags = $this->parseBitmask($wear, 'wear', self::KIND_ITEM);

        if ($flags === []) {
            return null;
        }

        if (count($flags) === 1) {
            return 'hint_wear_' . strtolower($flags[0]);
        }

        return 'hint_wear_multi';
    }

    public function tokenI18nKey(string $token): string
    {
        return 'admin.proto.tokens.' . $token;
    }

    public function itemTypeName(int $index): string
    {
        return $this->itemTypes()[$index] ?? 'ITEM_NONE';
    }

    public function itemSubtypeName(string $itemType, int $index): string
    {
        return ($this->profile->subtypesByType()[$itemType] ?? [])[$index] ?? '';
    }

    public function applyTypeName(int $index): string
    {
        return $this->profile->enumList(self::KIND_ITEM, 'apply_types')[$index] ?? '';
    }

    public function limitTypeName(int $index): string
    {
        return $this->profile->enumList(self::KIND_ITEM, 'limit_types')[$index] ?? 'LIMIT_NONE';
    }

    /**
     * Include current value in select options when unknown.
     *
     * @param list<string> $options
     * @return list<string>
     */
    public function ensureOption(array $options, string $current): array
    {
        $current = trim($current);

        if ($current === '' || $current === '0') {
            return $options;
        }

        if (in_array($current, $options, true)) {
            return $options;
        }

        return array_merge([$current], $options);
    }
}
