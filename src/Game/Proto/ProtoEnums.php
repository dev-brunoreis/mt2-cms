<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

class ProtoEnums
{
    public const KIND_ITEM = ProtoSchemas::KIND_ITEM;
    public const KIND_MOB = ProtoSchemas::KIND_MOB;

    /** @var list<string> */
    private const ITEM_TYPES = [
        'ITEM_NONE', 'ITEM_WEAPON', 'ITEM_ARMOR', 'ITEM_USE', 'ITEM_AUTOUSE', 'ITEM_MATERIAL',
        'ITEM_SPECIAL', 'ITEM_TOOL', 'ITEM_LOTTERY', 'ITEM_ELK', 'ITEM_METIN', 'ITEM_CONTAINER',
        'ITEM_FISH', 'ITEM_ROD', 'ITEM_RESOURCE', 'ITEM_CAMPFIRE', 'ITEM_UNIQUE', 'ITEM_SKILLBOOK',
        'ITEM_QUEST', 'ITEM_POLYMORPH', 'ITEM_TREASURE_BOX', 'ITEM_TREASURE_KEY', 'ITEM_SKILLFORGET',
        'ITEM_GIFTBOX', 'ITEM_PICK', 'ITEM_HAIR', 'ITEM_TOTEM', 'ITEM_BLEND', 'ITEM_COSTUME',
        'ITEM_DS', 'ITEM_SPECIAL_DS', 'ITEM_EXTRACT', 'ITEM_SECONDARY_COIN', 'ITEM_RING', 'ITEM_BELT',
    ];

    /** @var array<string, list<string>> */
    private const ITEM_SUBTYPES = [
        'ITEM_WEAPON' => [
            'WEAPON_SWORD', 'WEAPON_DAGGER', 'WEAPON_BOW', 'WEAPON_TWO_HANDED',
            'WEAPON_BELL', 'WEAPON_FAN', 'WEAPON_ARROW', 'WEAPON_MOUNT_SPEAR',
        ],
        'ITEM_ARMOR' => [
            'ARMOR_BODY', 'ARMOR_HEAD', 'ARMOR_SHIELD', 'ARMOR_WRIST', 'ARMOR_FOOTS',
            'ARMOR_NECK', 'ARMOR_EAR', 'ARMOR_NUM_TYPES',
        ],
        'ITEM_USE' => [
            'USE_POTION', 'USE_TALISMAN', 'USE_TUNING', 'USE_MOVE', 'USE_TREASURE_BOX', 'USE_MONEYBAG',
            'USE_BAIT', 'USE_ABILITY_UP', 'USE_AFFECT', 'USE_CREATE_STONE', 'USE_SPECIAL',
            'USE_POTION_NODELAY', 'USE_CLEAR', 'USE_INVISIBILITY', 'USE_DETACHMENT', 'USE_BUCKET',
            'USE_POTION_CONTINUE', 'USE_CLEAN_SOCKET', 'USE_CHANGE_ATTRIBUTE', 'USE_ADD_ATTRIBUTE',
            'USE_ADD_ACCESSORY_SOCKET', 'USE_PUT_INTO_ACCESSORY_SOCKET', 'USE_ADD_ATTRIBUTE2',
            'USE_RECIPE', 'USE_CHANGE_ATTRIBUTE2', 'USE_BIND', 'USE_UNBIND', 'USE_TIME_CHARGE_PER',
            'USE_TIME_CHARGE_FIX', 'USE_PUT_INTO_BELT_SOCKET', 'USE_PUT_INTO_RING_SOCKET',
        ],
        'ITEM_AUTOUSE' => [
            'AUTOUSE_POTION', 'AUTOUSE_ABILITY_UP', 'AUTOUSE_BOMB', 'AUTOUSE_GOLD',
            'AUTOUSE_MONEYBAG', 'AUTOUSE_TREASURE_BOX',
        ],
        'ITEM_MATERIAL' => [
            'MATERIAL_LEATHER', 'MATERIAL_BLOOD', 'MATERIAL_ROOT', 'MATERIAL_NEEDLE', 'MATERIAL_JEWEL',
            'MATERIAL_DS_REFINE_NORMAL', 'MATERIAL_DS_REFINE_BLESSED', 'MATERIAL_DS_REFINE_HOLLY',
        ],
        'ITEM_SPECIAL' => ['SPECIAL_MAP', 'SPECIAL_KEY', 'SPECIAL_DOC', 'SPECIAL_SPIRIT'],
        'ITEM_TOOL' => ['TOOL_FISHING_ROD'],
        'ITEM_LOTTERY' => ['LOTTERY_TICKET', 'LOTTERY_INSTANT'],
        'ITEM_METIN' => ['METIN_NORMAL', 'METIN_GOLD'],
        'ITEM_FISH' => ['FISH_ALIVE', 'FISH_DEAD'],
        'ITEM_RESOURCE' => [
            'RESOURCE_FISHBONE', 'RESOURCE_WATERSTONEPIECE', 'RESOURCE_WATERSTONE', 'RESOURCE_BLOOD_PEARL',
            'RESOURCE_BLUE_PEARL', 'RESOURCE_WHITE_PEARL', 'RESOURCE_BUCKET', 'RESOURCE_CRYSTAL',
            'RESOURCE_GEM', 'RESOURCE_STONE', 'RESOURCE_METIN', 'RESOURCE_ORE',
        ],
        'ITEM_UNIQUE' => [
            'UNIQUE_NONE', 'UNIQUE_BOOK', 'UNIQUE_SPECIAL_RIDE', 'UNIQUE_3', 'UNIQUE_4', 'UNIQUE_5',
            'UNIQUE_6', 'UNIQUE_7', 'UNIQUE_8', 'UNIQUE_9', 'USE_SPECIAL',
        ],
        'ITEM_COSTUME' => ['COSTUME_BODY', 'COSTUME_HAIR'],
        'ITEM_DS' => ['DS_SLOT1', 'DS_SLOT2', 'DS_SLOT3', 'DS_SLOT4', 'DS_SLOT5', 'DS_SLOT6'],
        'ITEM_SPECIAL_DS' => ['DS_SLOT1', 'DS_SLOT2', 'DS_SLOT3', 'DS_SLOT4', 'DS_SLOT5', 'DS_SLOT6'],
        'ITEM_EXTRACT' => ['EXTRACT_DRAGON_SOUL', 'EXTRACT_DRAGON_HEART'],
    ];

    /** @var list<string> */
    private const LIMIT_TYPES = [
        'LIMIT_NONE', 'LEVEL', 'STR', 'DEX', 'INT', 'CON', 'PC_BANG',
        'REAL_TIME', 'REAL_TIME_FIRST_USE', 'TIMER_BASED_ON_WEAR',
    ];

    /** @var list<string> */
    private const APPLY_TYPES = [
        'APPLY_NONE', 'APPLY_MAX_HP', 'APPLY_MAX_SP', 'APPLY_CON', 'APPLY_INT', 'APPLY_STR', 'APPLY_DEX',
        'APPLY_ATT_SPEED', 'APPLY_MOV_SPEED', 'APPLY_CAST_SPEED', 'APPLY_HP_REGEN', 'APPLY_SP_REGEN',
        'APPLY_POISON_PCT', 'APPLY_STUN_PCT', 'APPLY_SLOW_PCT', 'APPLY_CRITICAL_PCT', 'APPLY_PENETRATE_PCT',
        'APPLY_ATTBONUS_HUMAN', 'APPLY_ATTBONUS_ANIMAL', 'APPLY_ATTBONUS_ORC', 'APPLY_ATTBONUS_MILGYO',
        'APPLY_ATTBONUS_UNDEAD', 'APPLY_ATTBONUS_DEVIL', 'APPLY_STEAL_HP', 'APPLY_STEAL_SP',
        'APPLY_MANA_BURN_PCT', 'APPLY_DAMAGE_SP_RECOVER', 'APPLY_BLOCK', 'APPLY_DODGE', 'APPLY_RESIST_SWORD',
        'APPLY_RESIST_TWOHAND', 'APPLY_RESIST_DAGGER', 'APPLY_RESIST_BELL', 'APPLY_RESIST_FAN',
        'APPLY_RESIST_BOW', 'APPLY_RESIST_FIRE', 'APPLY_RESIST_ELEC', 'APPLY_RESIST_MAGIC', 'APPLY_RESIST_WIND',
        'APPLY_REFLECT_MELEE', 'APPLY_REFLECT_CURSE', 'APPLY_POISON_REDUCE', 'APPLY_KILL_SP_RECOVER',
        'APPLY_EXP_DOUBLE_BONUS', 'APPLY_GOLD_DOUBLE_BONUS', 'APPLY_ITEM_DROP_BONUS', 'APPLY_POTION_BONUS',
        'APPLY_KILL_HP_RECOVER', 'APPLY_IMMUNE_STUN', 'APPLY_IMMUNE_SLOW', 'APPLY_IMMUNE_FALL', 'APPLY_SKILL',
        'APPLY_BOW_DISTANCE', 'APPLY_ATT_GRADE_BONUS', 'APPLY_DEF_GRADE_BONUS', 'APPLY_MAGIC_ATT_GRADE',
        'APPLY_MAGIC_DEF_GRADE', 'APPLY_CURSE_PCT', 'APPLY_MAX_STAMINA', 'APPLY_ATTBONUS_WARRIOR',
        'APPLY_ATTBONUS_ASSASSIN', 'APPLY_ATTBONUS_SURA', 'APPLY_ATTBONUS_SHAMAN', 'APPLY_ATTBONUS_MONSTER',
        'APPLY_MALL_ATTBONUS', 'APPLY_MALL_DEFBONUS', 'APPLY_MALL_EXPBONUS', 'APPLY_MALL_ITEMBONUS',
        'APPLY_MALL_GOLDBONUS', 'APPLY_MAX_HP_PCT', 'APPLY_MAX_SP_PCT', 'APPLY_SKILL_DAMAGE_BONUS',
        'APPLY_NORMAL_HIT_DAMAGE_BONUS', 'APPLY_SKILL_DEFEND_BONUS', 'APPLY_NORMAL_HIT_DEFEND_BONUS',
        'APPLY_PC_BANG_EXP_BONUS', 'APPLY_PC_BANG_DROP_BONUS', 'APPLY_EXTRACT_HP_PCT', 'APPLY_RESIST_WARRIOR',
        'APPLY_RESIST_ASSASSIN', 'APPLY_RESIST_SURA', 'APPLY_RESIST_SHAMAN', 'APPLY_ENERGY', 'APPLY_DEF_GRADE',
        'APPLY_COSTUME_ATTR_BONUS', 'APPLY_MAGIC_ATTBONUS_PER', 'APPLY_MELEE_MAGIC_ATTBONUS_PER',
        'APPLY_RESIST_ICE', 'APPLY_RESIST_EARTH', 'APPLY_RESIST_DARK', 'APPLY_ANTI_CRITICAL_PCT',
        'APPLY_ANTI_PENETRATE_PCT',
    ];

    /** @var list<string> */
    private const ANTI_FLAGS = [
        'ANTI_FEMALE', 'ANTI_MALE', 'ANTI_MUSA', 'ANTI_ASSASSIN', 'ANTI_SURA', 'ANTI_MUDANG',
        'ANTI_GET', 'ANTI_DROP', 'ANTI_SELL', 'ANTI_EMPIRE_A', 'ANTI_EMPIRE_B', 'ANTI_EMPIRE_C',
        'ANTI_SAVE', 'ANTI_GIVE', 'ANTI_PKDROP', 'ANTI_STACK', 'ANTI_MYSHOP', 'ANTI_SAFEBOX',
    ];

    /** @var list<string> */
    private const ITEM_FLAGS = [
        'ITEM_TUNABLE', 'ITEM_SAVE', 'ITEM_STACKABLE', 'COUNT_PER_1GOLD', 'ITEM_SLOW_QUERY', 'ITEM_UNIQUE',
        'ITEM_MAKECOUNT', 'ITEM_IRREMOVABLE', 'CONFIRM_WHEN_USE', 'QUEST_USE', 'QUEST_USE_MULTIPLE',
        'QUEST_GIVE', 'ITEM_QUEST', 'LOG', 'STACKABLE', 'SLOW_QUERY', 'REFINEABLE', 'IRREMOVABLE',
        'ITEM_APPLICABLE',
    ];

    /** @var list<string> */
    private const WEAR_FLAGS = [
        'WEAR_BODY', 'WEAR_HEAD', 'WEAR_FOOTS', 'WEAR_WRIST', 'WEAR_WEAPON', 'WEAR_NECK', 'WEAR_EAR',
        'WEAR_SHIELD', 'WEAR_UNIQUE', 'WEAR_ARROW', 'WEAR_HAIR', 'WEAR_ABILITY',
    ];

    /** @var list<string> */
    private const ITEM_IMMUNE = ['PARA', 'CURSE', 'STUN', 'SLEEP', 'SLOW', 'POISON', 'TERROR'];

    /** @var list<string> */
    private const MOB_RANKS = ['PAWN', 'S_PAWN', 'KNIGHT', 'S_KNIGHT', 'BOSS', 'KING'];

    /** @var list<string> */
    private const MOB_TYPES = [
        'MONSTER', 'NPC', 'STONE', 'WARP', 'DOOR', 'BUILDING', 'PC', 'POLYMORPH_PC', 'HORSE', 'GOTO',
    ];

    /** @var list<string> */
    private const MOB_BATTLE_TYPES = [
        'MELEE', 'RANGE', 'MAGIC', 'SPECIAL', 'POWER', 'TANKER', 'SUPER_POWER', 'SUPER_TANKER',
    ];

    /** @var list<string> */
    private const MOB_SIZES = ['SMALL', 'MEDIUM', 'BIG'];

    /** @var list<string> */
    private const MOB_AI_FLAGS = [
        'AGGR', 'NOMOVE', 'COWARD', 'NOATTSHINSU', 'NOATTCHUNJO', 'NOATTJINNO', 'ATTMOB', 'BERSERK',
        'STONESKIN', 'GODSPEED', 'DEATHBLOW', 'REVIVE',
    ];

    /** @var list<string> */
    private const MOB_RACE_FLAGS = [
        'ANIMAL', 'UNDEAD', 'DEVIL', 'HUMAN', 'ORC', 'MILGYO', 'INSECT', 'FIRE', 'ICE', 'DESERT', 'TREE',
        'ATT_ELEC', 'ATT_FIRE', 'ATT_ICE', 'ATT_WIND', 'ATT_EARTH', 'ATT_DARK',
    ];

    /** @var list<string> */
    private const MOB_IMMUNE_FLAGS = ['STUN', 'SLOW', 'FALL', 'CURSE', 'POISON', 'TERROR', 'REFLECT'];

    /** @var array<string, array{separator: string, tokens: list<string>}> */
    private const BITMASK_FIELDS = [
        'antiflag' => ['separator' => '|', 'tokens' => self::ANTI_FLAGS],
        'flag' => ['separator' => '|', 'tokens' => self::ITEM_FLAGS],
        'wear' => ['separator' => '|', 'tokens' => self::WEAR_FLAGS],
        'immune' => ['separator' => '|', 'tokens' => self::ITEM_IMMUNE],
        'ai_flag' => ['separator' => ',', 'tokens' => self::MOB_AI_FLAGS],
        'race_flag' => ['separator' => ',', 'tokens' => self::MOB_RACE_FLAGS],
        'immune_flag' => ['separator' => ',', 'tokens' => self::MOB_IMMUNE_FLAGS],
    ];

    /**
     * @return list<string>
     */
    public static function itemTypes(): array
    {
        return self::ITEM_TYPES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function itemSubtypesByType(): array
    {
        return self::ITEM_SUBTYPES;
    }

    /**
     * @return list<string>
     */
    public static function subtypesForItemType(string $type): array
    {
        return self::ITEM_SUBTYPES[$type] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function optionsForField(string $kind, string $fieldKey): array
    {
        if ($kind === self::KIND_ITEM) {
            return match ($fieldKey) {
                'type' => self::ITEM_TYPES,
                'limit_type0', 'limit_type1' => self::LIMIT_TYPES,
                'apply_type0', 'apply_type1', 'apply_type2' => self::APPLY_TYPES,
                default => [],
            };
        }

        return match ($fieldKey) {
            'rank' => self::MOB_RANKS,
            'type' => self::MOB_TYPES,
            'battle_type' => self::MOB_BATTLE_TYPES,
            'size' => self::MOB_SIZES,
            default => [],
        };
    }

    public static function widgetForField(string $kind, string $fieldKey): string
    {
        if (isset(self::BITMASK_FIELDS[$fieldKey])) {
            return 'bitmask';
        }

        if ($kind === self::KIND_ITEM && $fieldKey === 'subtype') {
            return 'subtype';
        }

        if (self::optionsForField($kind, $fieldKey) !== []) {
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
    public static function parseBitmask(string $value, string $fieldKey): array
    {
        $value = trim($value);

        if ($value === '' || strcasecmp($value, 'NONE') === 0) {
            return [];
        }

        $separator = self::BITMASK_FIELDS[$fieldKey]['separator'] ?? '|';
        $parts = array_map('trim', explode($separator, $value));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    public static function joinBitmask(array $tokens, string $fieldKey): string
    {
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn (string $t): bool => $t !== ''));

        if ($tokens === []) {
            return in_array($fieldKey, ['ai_flag', 'race_flag', 'immune_flag'], true) ? '' : 'NONE';
        }

        $separator = self::BITMASK_FIELDS[$fieldKey]['separator'] ?? '|';

        return implode($separator, $tokens);
    }

    /**
     * @return list<string>
     */
    public static function bitmaskTokens(string $fieldKey): array
    {
        return self::BITMASK_FIELDS[$fieldKey]['tokens'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function valueLabelsFor(string $itemType, string $subtype = ''): array
    {
        $fallback = static fn (int $n): string => 'Value ' . $n;

        if ($itemType === 'ITEM_WEAPON') {
            return [
                'value0' => 'value_extra_0',
                'value1' => 'value_min_damage',
                'value2' => 'value_max_damage',
                'value3' => 'value_attack_speed',
                'value4' => 'value_magic_attack',
                'value5' => 'value_extra_5',
            ];
        }

        if ($itemType === 'ITEM_ARMOR') {
            return [
                'value0' => 'value_extra_0',
                'value1' => 'value_defence',
                'value2' => 'value_extra_2',
                'value3' => 'value_extra_3',
                'value4' => 'value_extra_4',
                'value5' => 'value_defence_bonus',
            ];
        }

        if ($itemType === 'ITEM_COSTUME' && $subtype === 'COSTUME_HAIR') {
            return [
                'value0' => 'value_extra_0',
                'value1' => 'value_extra_1',
                'value2' => 'value_extra_2',
                'value3' => 'value_hair_shape',
                'value4' => 'value_extra_4',
                'value5' => 'value_extra_5',
            ];
        }

        if ($itemType === 'ITEM_USE') {
            return [
                'value0' => 'value_effect_type',
                'value1' => 'value_duration_or_qty',
                'value2' => 'value_magnitude',
                'value3' => 'value_extra_3',
                'value4' => 'value_extra_4',
                'value5' => 'value_extra_5',
            ];
        }

        return [
            'value0' => 'value_0',
            'value1' => 'value_1',
            'value2' => 'value_2',
            'value3' => 'value_3',
            'value4' => 'value_4',
            'value5' => 'value_5',
        ];
    }

    public static function equipSlotHint(string $itemType, string $subtype, string $wear): ?string
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

        $flags = self::parseBitmask($wear, 'wear');

        if ($flags === []) {
            return null;
        }

        if (count($flags) === 1) {
            return 'hint_wear_' . strtolower($flags[0]);
        }

        return 'hint_wear_multi';
    }

    public static function tokenI18nKey(string $token): string
    {
        return 'admin.proto.tokens.' . $token;
    }

    public static function itemTypeName(int $index): string
    {
        return self::ITEM_TYPES[$index] ?? 'ITEM_NONE';
    }

    public static function itemSubtypeName(string $itemType, int $index): string
    {
        return (self::ITEM_SUBTYPES[$itemType] ?? [])[$index] ?? '';
    }

    public static function applyTypeName(int $index): string
    {
        return self::APPLY_TYPES[$index] ?? '';
    }

    public static function limitTypeName(int $index): string
    {
        return self::LIMIT_TYPES[$index] ?? 'LIMIT_NONE';
    }

    /**
     * Include current value in select options when unknown.
     *
     * @param list<string> $options
     * @return list<string>
     */
    public static function ensureOption(array $options, string $current): array
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
