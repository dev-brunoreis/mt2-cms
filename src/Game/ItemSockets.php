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

    /**
     * @param list<int> $sockets socket0, socket1, socket2
     * @return list<array<string, mixed>>
     */
    public static function describe(
        int $type,
        int $subtype,
        int $limitType,
        int $value0,
        int $value2,
        array $sockets,
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
            return self::accessorySockets($sockets);
        }

        $entries = [];

        if (self::isTimeLimit($limitType) && $sockets[0] > 0) {
            $entries[] = self::timeEntry($sockets[0], false);
            $sockets[0] = 0;
        }

        foreach ($sockets as $value) {
            $stone = self::stoneEntry($value);

            if ($stone !== null) {
                $entries[] = $stone;
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
     * @return list<array<string, mixed>>
     */
    private static function accessorySockets(array $sockets): array
    {
        $filled = max(0, $sockets[0]);
        $max = max(0, $sockets[1]);

        if ($filled < 1 && $max < 1) {
            return [];
        }

        $entries = [[
            'kind' => 'accessory',
            'filled' => $filled,
            'max' => max($max, $filled),
            'value' => $filled,
        ]];

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
