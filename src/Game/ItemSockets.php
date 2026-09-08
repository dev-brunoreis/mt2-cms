<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

class ItemSockets
{
    public const BROKEN_METIN = 28960;

    private const TYPE_WEAPON = 1;
    private const TYPE_ARMOR = 2;
    private const TYPE_USE = 3;
    private const TYPE_UNIQUE = 16;
    private const TYPE_COSTUME = 28;
    private const TYPE_DS = 29;
    private const TYPE_SPECIAL_DS = 30;
    private const TYPE_RING = 33;
    private const TYPE_BELT = 34;

    private const ARMOR_WRIST = 3;
    private const ARMOR_NECK = 5;
    private const ARMOR_EAR = 6;

    private const USE_SPECIAL = 10;
    private const AUTO_RECOVERY_MIN_CHARGE = 10000;

    private const LIMIT_REAL_TIME = 7;
    private const LIMIT_REAL_TIME_FIRST_USE = 8;
    private const LIMIT_TIMER_BASED_ON_WEAR = 9;

    private const UNIX_THRESHOLD = 1_000_000_000;
    private const ACCESSORY_SOCKET_MAX = 3;
    private const BELT_MATERIAL_VNUM = 18900;

    /** @var list<int> */
    private const ACCESSORY_MATERIALS = [
        50623, 50624, 50625, 50626, 50627, 50628, 50629, 50630,
        50631, 50632, 50633, 50634, 50635, 50636, 50637, 50638,
    ];

    /** @var list<array{0: int, 1: int, 2: int, 3: int}> jewel, wrist, neck, ear */
    private const JEWEL_ACCESSORIES = [
        [50634, 14420, 16220, 17220],
        [50635, 14500, 16500, 17500],
        [50636, 14520, 16520, 17520],
        [50637, 14540, 16540, 17540],
        [50638, 14560, 16560, 17560],
    ];

    /**
     * @param list<int> $sockets socket0, socket1, socket2
     * @param list<array{type: int, value: int}> $applies host proto apply slots
     * @return list<array<string, mixed>>
     */
    public static function describe(
        int $type,
        int $subtype,
        int $limitType,
        int $value0,
        int $value2,
        array $sockets,
        int $vnum = 0,
        array $applies = [],
    ): array {
        $sockets = array_pad(array_map(
            static fn (mixed $value): int => (int) $value,
            array_slice($sockets, 0, 3),
        ), 3, 0);

        if (self::isAutoRecovery($type, $subtype, $value0, $sockets)) {
            return self::chargeSockets($value0, $sockets);
        }

        if (self::isTimeBoundType($type)) {
            return self::timeBoundSockets($type, $value2, $sockets);
        }

        if ($type === self::TYPE_DS) {
            return self::dragonSoulSockets($sockets);
        }

        if (self::isAccessoryType($type, $subtype)) {
            return self::accessorySockets($sockets, $vnum, $type, $subtype, $applies);
        }

        $entries = [];

        if (self::isTimeLimit($limitType) && $sockets[0] > 0) {
            $entries[] = self::timeEntry($sockets[0], false);
            $sockets[0] = 0;
        }

        $stones = [];

        foreach ($sockets as $value) {
            $stone = self::stoneEntry($value);

            if ($stone !== null) {
                $stones[] = $stone;
            }
        }

        foreach ($stones as $stone) {
            if (in_array($stone['kind'], ['item', 'broken'], true)) {
                return array_merge($entries, $stones);
            }
        }

        return $entries;
    }

    /**
     * Auto HP/SP elixirs store used/max charge in sockets, not stones.
     *
     * @param list<int> $sockets
     */
    private static function isAutoRecovery(int $type, int $subtype, int $value0, array $sockets): bool
    {
        if ($type !== self::TYPE_USE || $subtype !== self::USE_SPECIAL) {
            return false;
        }

        if ($sockets[0] !== 0 && $sockets[0] !== 1) {
            return false;
        }

        return max($value0, $sockets[2]) >= self::AUTO_RECOVERY_MIN_CHARGE;
    }

    /**
     * @param list<int> $sockets
     * @return list<array<string, mixed>>
     */
    private static function chargeSockets(int $value0, array $sockets): array
    {
        $full = $sockets[2] > 0 ? $sockets[2] : $value0;
        $used = max(0, $sockets[1]);

        if ($full < 1) {
            return [];
        }

        return [[
            'kind' => 'charge',
            'used' => $used,
            'full' => $full,
            'remaining' => max(0, $full - $used),
            'active' => $sockets[0] === 1,
            'value' => $full,
        ]];
    }

    private static function isTimeBoundType(int $type): bool
    {
        return in_array($type, [self::TYPE_UNIQUE, self::TYPE_COSTUME, self::TYPE_SPECIAL_DS], true);
    }

    private static function isAccessoryType(int $type, int $subtype): bool
    {
        if ($type === self::TYPE_BELT || $type === self::TYPE_RING) {
            return true;
        }

        return $type === self::TYPE_ARMOR && in_array($subtype, [
            self::ARMOR_WRIST,
            self::ARMOR_NECK,
            self::ARMOR_EAR,
        ], true);
    }

    private static function isTimeLimit(int $limitType): bool
    {
        return in_array($limitType, [
            self::LIMIT_REAL_TIME,
            self::LIMIT_REAL_TIME_FIRST_USE,
            self::LIMIT_TIMER_BASED_ON_WEAR,
        ], true);
    }

    /**
     * @param list<int> $sockets
     * @return list<array<string, mixed>>
     */
    private static function timeBoundSockets(int $type, int $uniqueValue2, array $sockets): array
    {
        $remain = $sockets[2] > 0 ? $sockets[2] : $sockets[0];

        if ($remain < 1) {
            return [];
        }

        $asMinutes = $type === self::TYPE_UNIQUE
            && $uniqueValue2 === 0
            && $remain < self::UNIX_THRESHOLD;

        return [self::timeEntry($remain, $asMinutes)];
    }

    /**
     * @param list<int> $sockets
     * @return list<array<string, mixed>>
     */
    private static function dragonSoulSockets(array $sockets): array
    {
        $entries = [];

        if ($sockets[0] > 0) {
            $entries[] = self::timeEntry($sockets[0], false);
        }

        $entries[] = [
            'kind' => $sockets[2] > 0 ? 'ds_active' : 'ds_inactive',
            'value' => $sockets[2],
        ];

        return $entries;
    }

    /**
     * @param list<int> $sockets
     * @param list<array{type: int, value: int}> $applies
     * @return list<array<string, mixed>>
     */
    private static function accessorySockets(
        array $sockets,
        int $vnum,
        int $type,
        int $subtype,
        array $applies,
    ): array {
        $filled = max(0, min(self::ACCESSORY_SOCKET_MAX, $sockets[0]));
        $max = max(0, min(self::ACCESSORY_SOCKET_MAX, $sockets[1]));
        $max = max($max, $filled);

        if ($filled < 1 && $max < 1) {
            return [];
        }

        $material = self::accessoryMaterialVnum($vnum, $type, $subtype);

        if ($material < 1) {
            $entries = [[
                'kind' => 'accessory',
                'filled' => $filled,
                'max' => $max,
                'value' => $filled,
            ]];
        } else {
            $entries = [];
            $increments = self::accessoryApplyIncrements($applies);

            for ($slot = 0; $slot < $max; $slot++) {
                if ($slot < $filled) {
                    $entry = [
                        'kind' => 'item',
                        'value' => $material,
                        'name' => '',
                    ];
                    $slotApplies = $increments[$slot] ?? [];

                    if ($slotApplies !== []) {
                        $entry['applies'] = ItemStats::applyEntries($slotApplies);
                    }

                    $entries[] = $entry;
                    continue;
                }

                $entries[] = [
                    'kind' => 'empty',
                    'value' => 1,
                ];
            }
        }

        if ($filled > 0 && $sockets[2] > 0) {
            $entries[] = [
                'kind' => 'degrade',
                'value' => $sockets[2],
                'seconds' => $sockets[2],
            ];
        }

        return $entries;
    }

    /**
     * Accessory diamonds add 10% / 20% / 40% of the host apply, shown per slot as the increment.
     *
     * @param list<array{type: int, value: int}> $applies
     * @return list<list<array{type: int, value: int}>>
     */
    private static function accessoryApplyIncrements(array $applies): array
    {
        $percents = [10, 20, 40];
        $perSlot = array_fill(0, self::ACCESSORY_SOCKET_MAX, []);

        foreach ($applies as $apply) {
            $type = (int) ($apply['type'] ?? 0);
            $value = (int) ($apply['value'] ?? 0);

            if ($type < 1 || $value === 0) {
                continue;
            }

            $levels = [0];

            foreach ($percents as $index => $percent) {
                $levels[] = max($index + 1, intdiv($value * $percent, 100));
            }

            for ($slot = 0; $slot < self::ACCESSORY_SOCKET_MAX; $slot++) {
                $increment = $levels[$slot + 1] - $levels[$slot];

                if ($increment !== 0) {
                    $perSlot[$slot][] = ['type' => $type, 'value' => $increment];
                }
            }
        }

        return $perSlot;
    }

    private static function accessoryMaterialVnum(int $vnum, int $type, int $subtype): int
    {
        if ($type === self::TYPE_BELT) {
            return self::BELT_MATERIAL_VNUM;
        }

        if ($type !== self::TYPE_ARMOR || $vnum < 1) {
            return 0;
        }

        $base = intdiv($vnum, 10) * 10;

        foreach (self::JEWEL_ACCESSORIES as $info) {
            $match = match ($subtype) {
                self::ARMOR_WRIST => $info[1],
                self::ARMOR_NECK => $info[2],
                self::ARMOR_EAR => $info[3],
                default => 0,
            };

            if ($match > 0 && $match === $base) {
                return $info[0];
            }
        }

        if ($vnum >= 16210 && $vnum <= 16219) {
            return 50625;
        }

        $offset = match ($subtype) {
            self::ARMOR_WRIST => $vnum - 14000,
            self::ARMOR_NECK => $vnum - 16000,
            self::ARMOR_EAR => $vnum - 17000,
            default => -1,
        };

        if ($offset < 0) {
            return 0;
        }

        $index = intdiv($offset, 20);

        if ($index < 0 || $index >= count(self::ACCESSORY_MATERIALS)) {
            $index = intdiv($offset - 170, 20);
        }

        if ($index < 0 || $index >= count(self::ACCESSORY_MATERIALS)) {
            return 0;
        }

        return self::ACCESSORY_MATERIALS[$index];
    }

    /**
     * @return array<string, mixed>
     */
    private static function timeEntry(int $value, bool $asMinutes): array
    {
        if ($value >= self::UNIX_THRESHOLD) {
            return [
                'kind' => 'expires',
                'value' => $value,
            ];
        }

        return [
            'kind' => 'remaining',
            'value' => $value,
            'seconds' => $asMinutes ? $value * 60 : $value,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function stoneEntry(int $value): ?array
    {
        if ($value < 1) {
            return null;
        }

        if ($value === 1 || $value === 2) {
            return [
                'kind' => 'empty',
                'value' => $value,
            ];
        }

        if ($value === self::BROKEN_METIN) {
            return [
                'kind' => 'broken',
                'value' => $value,
            ];
        }

        return [
            'kind' => 'item',
            'value' => $value,
            'name' => '',
        ];
    }
}
