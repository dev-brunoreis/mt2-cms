<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

class ProtoSchemas
{
    public const KIND_ITEM = 'item';
    public const KIND_MOB = 'mob';

    /**
     * @return list<string>
     */
    public static function columns(string $kind): array
    {
        return $kind === self::KIND_MOB ? self::mobColumns() : self::itemColumns();
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(string $kind): array
    {
        $record = [];

        foreach (self::columns($kind) as $key) {
            $record[$key] = '';
        }

        $record['locale_name'] = '';

        if ($kind === self::KIND_MOB) {
            $record['rank'] = 'PAWN';
            $record['type'] = 'MONSTER';
            $record['battle_type'] = 'MELEE';
            $record['level'] = '1';
            $record['mount_capacity'] = '0';
            $record['empire'] = '0';
            $record['on_click'] = '0';
            $record['attack_speed'] = '100';
            $record['move_speed'] = '100';
            $record['dam_multiply'] = '1';

            return $record;
        }

        $record['type'] = 'ITEM_NONE';
        $record['subtype'] = '0';
        $record['size'] = '1';
        $record['antiflag'] = 'NONE';
        $record['flag'] = 'NONE';
        $record['wear'] = 'NONE';
        $record['immune'] = 'NONE';
        $record['gold'] = '0';
        $record['shop_buy_price'] = '0';
        $record['refine'] = '0';
        $record['refine_set'] = '0';
        $record['magic_pct'] = '0';
        $record['limit_type0'] = 'LIMIT_NONE';
        $record['limit_value0'] = '0';
        $record['limit_type1'] = 'LIMIT_NONE';
        $record['limit_value1'] = '0';
        $record['apply_type0'] = 'APPLY_NONE';
        $record['apply_value0'] = '0';
        $record['apply_type1'] = 'APPLY_NONE';
        $record['apply_value1'] = '0';
        $record['apply_type2'] = 'APPLY_NONE';
        $record['apply_value2'] = '0';
        $record['value0'] = '0';
        $record['value1'] = '0';
        $record['value2'] = '0';
        $record['value3'] = '0';
        $record['value4'] = '0';
        $record['value5'] = '0';
        $record['specular'] = '0';
        $record['socket'] = '0';
        $record['addon_type'] = '0';

        return $record;
    }

    /**
     * @return list<string>
     */
    public static function listColumns(string $kind): array
    {
        if ($kind === self::KIND_MOB) {
            return ['vnum', 'locale_name', 'type', 'rank', 'level', 'max_hp', 'exp'];
        }

        return ['vnum', 'locale_name', 'type', 'subtype', 'size', 'gold'];
    }

    /**
     * @return list<array{id: string, fields: list<string>}>
     */
    public static function formTabs(string $kind): array
    {
        if ($kind === self::KIND_MOB) {
            return [
                [
                    'id' => 'identity',
                    'fields' => [
                        'vnum', 'locale_name', 'rank', 'type', 'battle_type', 'level',
                        'size', 'folder', 'empire', 'on_click', 'mount_capacity',
                    ],
                ],
                [
                    'id' => 'combat',
                    'fields' => [
                        'st', 'dx', 'ht', 'iq', 'damage_min', 'damage_max', 'max_hp',
                        'def', 'exp', 'attack_speed', 'move_speed', 'dam_multiply',
                        'attack_range', 'regen_cycle', 'regen_percent',
                    ],
                ],
                [
                    'id' => 'behavior',
                    'fields' => [
                        'ai_flag', 'race_flag', 'immune_flag', 'gold_min', 'gold_max',
                        'aggressive_hp_pct', 'aggressive_sight', 'drop_item',
                        'resurrection_vnum', 'polymorph_item', 'summon', 'drain_sp',
                        'mob_color',
                    ],
                ],
                [
                    'id' => 'resists',
                    'fields' => [
                        'enchant_curse', 'enchant_slow', 'enchant_poison', 'enchant_stun',
                        'enchant_critical', 'enchant_penetrate', 'resist_sword',
                        'resist_twohand', 'resist_dagger', 'resist_bell', 'resist_fan',
                        'resist_bow', 'resist_fire', 'resist_elect', 'resist_magic',
                        'resist_wind', 'resist_poison',
                    ],
                ],
                [
                    'id' => 'skills',
                    'fields' => [
                        'skill_level0', 'skill_vnum0', 'skill_level1', 'skill_vnum1',
                        'skill_level2', 'skill_vnum2', 'skill_level3', 'skill_vnum3',
                        'skill_level4', 'skill_vnum4', 'sp_berserk', 'sp_stoneskin',
                        'sp_godspeed', 'sp_deathblow', 'sp_revive',
                    ],
                ],
            ];
        }

        return [
            [
                'id' => 'identity',
                'fields' => ['vnum', 'locale_name', 'type', 'subtype', 'size'],
            ],
            [
                'id' => 'flags',
                'fields' => ['antiflag', 'flag', 'wear', 'immune'],
            ],
            [
                'id' => 'economy',
                'fields' => [
                    'gold', 'shop_buy_price', 'refine', 'refine_set', 'magic_pct',
                    'specular', 'socket', 'addon_type',
                ],
            ],
            [
                'id' => 'applies',
                'fields' => [
                    'limit_type0', 'limit_value0', 'limit_type1', 'limit_value1',
                    'apply_type0', 'apply_value0', 'apply_type1', 'apply_value1',
                    'apply_type2', 'apply_value2',
                ],
            ],
            [
                'id' => 'values',
                'fields' => ['value0', 'value1', 'value2', 'value3', 'value4', 'value5'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function itemColumns(): array
    {
        return [
            'vnum', 'name', 'type', 'subtype', 'size', 'antiflag', 'flag', 'wear', 'immune',
            'gold', 'shop_buy_price', 'refine', 'refine_set', 'magic_pct',
            'limit_type0', 'limit_value0', 'limit_type1', 'limit_value1',
            'apply_type0', 'apply_value0', 'apply_type1', 'apply_value1', 'apply_type2', 'apply_value2',
            'value0', 'value1', 'value2', 'value3', 'value4', 'value5',
            'specular', 'socket', 'addon_type',
        ];
    }

    /**
     * @return list<string>
     */
    private static function mobColumns(): array
    {
        return [
            'vnum', 'name', 'rank', 'type', 'battle_type', 'level', 'size', 'ai_flag',
            'mount_capacity', 'race_flag', 'immune_flag', 'empire', 'folder', 'on_click',
            'st', 'dx', 'ht', 'iq', 'damage_min', 'damage_max', 'max_hp', 'regen_cycle',
            'regen_percent', 'gold_min', 'gold_max', 'exp', 'def', 'attack_speed', 'move_speed',
            'aggressive_hp_pct', 'aggressive_sight', 'attack_range', 'drop_item', 'resurrection_vnum',
            'enchant_curse', 'enchant_slow', 'enchant_poison', 'enchant_stun', 'enchant_critical',
            'enchant_penetrate', 'resist_sword', 'resist_twohand', 'resist_dagger', 'resist_bell',
            'resist_fan', 'resist_bow', 'resist_fire', 'resist_elect', 'resist_magic', 'resist_wind',
            'resist_poison', 'dam_multiply', 'summon', 'drain_sp', 'mob_color', 'polymorph_item',
            'skill_level0', 'skill_vnum0', 'skill_level1', 'skill_vnum1', 'skill_level2', 'skill_vnum2',
            'skill_level3', 'skill_vnum3', 'skill_level4', 'skill_vnum4',
            'sp_berserk', 'sp_stoneskin', 'sp_godspeed', 'sp_deathblow', 'sp_revive',
        ];
    }
}
