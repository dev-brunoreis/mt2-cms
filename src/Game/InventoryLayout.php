<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

class InventoryLayout
{
    public const INVENTORY_COLUMNS = 5;
    public const INVENTORY_ROWS = 9;
    public const BELT_COLUMNS = 4;
    public const BELT_ROWS = 4;
    public const DS_COLUMNS = 8;
    public const DS_ROWS = 4;
    public const DS_PAGE_SIZE = 32;
    public const DS_KIND_COUNT = 6;
    public const DS_GRADE_COUNT = 5;
    public const DS_SLOT_COUNT = 6;
    public const DS_DECK_COUNT = 2;
    public const WEAR_MAX_NUM = 32;

    /** Paperdoll size matching client EquipmentSlot (150×182). */
    public const EQUIPMENT_WIDTH = 150;
    public const EQUIPMENT_HEIGHT = 182;

    public const COSTUME_WIDTH = 127;
    public const COSTUME_HEIGHT = 145;

    /** Dragon Soul equipment board from dragonsoulwindow.py. */
    public const DS_EQUIP_WIDTH = 287;
    /** Cropped to the circle + deck buttons (client y≈230). */
    public const DS_EQUIP_HEIGHT = 255;

    /**
     * Slot positions from Eternexus `inventorywindow.py` / `equipmentdialog.py`.
     * Keys are `item.pos` in the EQUIPMENT window.
     *
     * @var array<int, array{x: int, y: int, w: int, h: int, key: string}>
     */
    public const EQUIPMENT_SLOTS = [
        0 => ['x' => 39, 'y' => 37, 'w' => 32, 'h' => 64, 'key' => 'body'],
        1 => ['x' => 39, 'y' => 2, 'w' => 32, 'h' => 32, 'key' => 'head'],
        2 => ['x' => 39, 'y' => 145, 'w' => 32, 'h' => 32, 'key' => 'foots'],
        3 => ['x' => 75, 'y' => 67, 'w' => 32, 'h' => 32, 'key' => 'wrist'],
        4 => ['x' => 3, 'y' => 3, 'w' => 32, 'h' => 96, 'key' => 'weapon'],
        5 => ['x' => 114, 'y' => 84, 'w' => 32, 'h' => 32, 'key' => 'neck'],
        6 => ['x' => 114, 'y' => 52, 'w' => 32, 'h' => 32, 'key' => 'ear'],
        7 => ['x' => 2, 'y' => 113, 'w' => 32, 'h' => 32, 'key' => 'unique1'],
        8 => ['x' => 75, 'y' => 113, 'w' => 32, 'h' => 32, 'key' => 'unique2'],
        9 => ['x' => 114, 'y' => 1, 'w' => 32, 'h' => 32, 'key' => 'arrow'],
        10 => ['x' => 75, 'y' => 35, 'w' => 32, 'h' => 32, 'key' => 'shield'],
        23 => ['x' => 39, 'y' => 106, 'w' => 32, 'h' => 32, 'key' => 'belt'],
    ];

    /**
     * Costume slots from Eternexus `costumewindow.py` (WEAR_COSTUME_BODY/HAIR).
     *
     * @var array<int, array{x: int, y: int, w: int, h: int, key: string}>
     */
    public const COSTUME_SLOTS = [
        19 => ['x' => 61, 'y' => 45, 'w' => 32, 'h' => 64, 'key' => 'costume_body'],
        20 => ['x' => 61, 'y' => 8, 'w' => 32, 'h' => 32, 'key' => 'costume_hair'],
    ];

    /**
     * Equipped dragon soul slot offsets within one deck (dragonsoulwindow.py).
     *
     * @var list<array{x: int, y: int, w: int, h: int, key: string}>
     */
    public const DS_EQUIP_SLOTS = [
        ['x' => 128, 'y' => 53, 'w' => 32, 'h' => 32, 'key' => 'ds_slot1'],
        ['x' => 59, 'y' => 93, 'w' => 32, 'h' => 32, 'key' => 'ds_slot2'],
        ['x' => 59, 'y' => 179, 'w' => 32, 'h' => 32, 'key' => 'ds_slot3'],
        ['x' => 128, 'y' => 219, 'w' => 32, 'h' => 32, 'key' => 'ds_slot4'],
        ['x' => 194, 'y' => 179, 'w' => 32, 'h' => 32, 'key' => 'ds_slot5'],
        ['x' => 194, 'y' => 93, 'w' => 32, 'h' => 32, 'key' => 'ds_slot6'],
    ];

    /** @var list<string> */
    public const DS_KIND_KEYS = [
        'diamond',
        'ruby',
        'jade',
        'sapphire',
        'garnet',
        'onyx',
    ];

    /** @var list<string> */
    public const DS_GRADE_KEYS = [
        'rough',
        'cut',
        'rare',
        'antique',
        'legendary',
    ];

    /** @var list<string> */
    public const DS_DECK_KEYS = [
        'heaven',
        'earth',
    ];

    /** @var list<string> */
    private const PAGE_LABELS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array{
     *   equipment: array{width: int, height: int, slots: list<array<string, mixed>>, has_item: bool},
     *   costume: array{width: int, height: int, slots: list<array<string, mixed>>, has_item: bool},
     *   extra: list<array<string, mixed>>,
     *   inventory: array{columns: int, rows: int, pages: list<array<string, mixed>>},
     *   belt: array{columns: int, rows: int, pages: list<array<string, mixed>>},
     *   dragonSoul: array{
     *     columns: int,
     *     rows: int,
     *     decks: list<array{id: string, key: string, width: int, height: int, slots: list<array<string, mixed>>, has_item: bool}>,
     *     kinds: list<array{id: string, key: string, grades: list<array{id: string, key: string, cells: list<array<string, mixed>>}>}>
     *   }
     * }
     */
    public static function forCharacter(array $grouped): array
    {
        $equipment = $grouped['EQUIPMENT'] ?? [];
        $dsEquipPos = self::dragonSoulEquipPositions();
        $placed = array_merge(
            array_keys(self::EQUIPMENT_SLOTS),
            array_keys(self::COSTUME_SLOTS),
            $dsEquipPos,
        );

        return [
            'equipment' => self::paperdoll(self::EQUIPMENT_SLOTS, $equipment, self::EQUIPMENT_WIDTH, self::EQUIPMENT_HEIGHT),
            'costume' => self::paperdoll(self::COSTUME_SLOTS, $equipment, self::COSTUME_WIDTH, self::COSTUME_HEIGHT),
            'extra' => self::unplaced($equipment, $placed),
            'inventory' => self::pagedGrid($grouped['INVENTORY'] ?? [], self::INVENTORY_COLUMNS, self::INVENTORY_ROWS, 2),
            'belt' => self::pagedGrid($grouped['BELT_INVENTORY'] ?? [], self::BELT_COLUMNS, self::BELT_ROWS, 1),
            'dragonSoul' => self::dragonSoulLayout($equipment, $grouped['DRAGON_SOUL_INVENTORY'] ?? []),
        ];
    }

    /**
     * Public-safe boards: worn gear + costume only (no bags, belt grid, or DS inventory).
     *
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array{
     *   equipment: array{width: int, height: int, slots: list<array<string, mixed>>, has_item: bool},
     *   costume: array{width: int, height: int, slots: list<array<string, mixed>>, has_item: bool}
     * }
     */
    public static function forPublicProfile(array $grouped): array
    {
        $equipment = $grouped['EQUIPMENT'] ?? [];

        return [
            'equipment' => self::paperdoll(self::EQUIPMENT_SLOTS, $equipment, self::EQUIPMENT_WIDTH, self::EQUIPMENT_HEIGHT),
            'costume' => self::paperdoll(self::COSTUME_SLOTS, $equipment, self::COSTUME_WIDTH, self::COSTUME_HEIGHT),
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $grouped
     * @return array{
     *   safebox: array{columns: int, rows: int, pages: list<array<string, mixed>>},
     *   mall: array{columns: int, rows: int, pages: list<array<string, mixed>>}
     * }
     */
    public static function forAccount(array $grouped, int $safeboxPages): array
    {
        return [
            'safebox' => self::pagedGrid($grouped['SAFEBOX'] ?? [], self::INVENTORY_COLUMNS, self::INVENTORY_ROWS, max(0, $safeboxPages)),
            'mall' => self::pagedGrid($grouped['MALL'] ?? [], self::INVENTORY_COLUMNS, self::INVENTORY_ROWS, 0),
        ];
    }

    /**
     * @return list<int>
     */
    private static function dragonSoulEquipPositions(): array
    {
        $positions = [];

        for ($deck = 0; $deck < self::DS_DECK_COUNT; $deck++) {
            for ($slot = 0; $slot < self::DS_SLOT_COUNT; $slot++) {
                $positions[] = self::WEAR_MAX_NUM + ($deck * self::DS_SLOT_COUNT) + $slot;
            }
        }

        return $positions;
    }

    /**
     * @param list<array<string, mixed>> $equipment
     * @param list<array<string, mixed>> $dsItems
     * @return array{
     *   columns: int,
     *   rows: int,
     *   decks: list<array{id: string, key: string, width: int, height: int, slots: list<array<string, mixed>>, has_item: bool}>,
     *   kinds: list<array{id: string, key: string, grades: list<array{id: string, key: string, cells: list<array<string, mixed>>}>}>
     * }
     */
    private static function dragonSoulLayout(array $equipment, array $dsItems): array
    {
        $byPos = self::indexByPos($equipment);
        $decks = [];

        for ($deck = 0; $deck < self::DS_DECK_COUNT; $deck++) {
            $slots = [];
            $hasItem = false;

            foreach (self::DS_EQUIP_SLOTS as $index => $slot) {
                $pos = self::WEAR_MAX_NUM + ($deck * self::DS_SLOT_COUNT) + $index;
                $item = $byPos[$pos] ?? null;

                if ($item !== null) {
                    $hasItem = true;
                }

                $slots[] = [
                    'pos' => $pos,
                    'x' => $slot['x'],
                    'y' => $slot['y'],
                    'w' => $slot['w'],
                    'h' => $slot['h'],
                    'key' => $slot['key'],
                    'item' => $item,
                ];
            }

            $decks[] = [
                'id' => (string) $deck,
                'key' => self::DS_DECK_KEYS[$deck],
                'width' => self::DS_EQUIP_WIDTH,
                'height' => self::DS_EQUIP_HEIGHT,
                'slots' => $slots,
                'has_item' => $hasItem,
            ];
        }

        $dsByPos = self::indexByPos($dsItems);
        $kinds = [];

        for ($kind = 0; $kind < self::DS_KIND_COUNT; $kind++) {
            $grades = [];

            for ($grade = 0; $grade < self::DS_GRADE_COUNT; $grade++) {
                $base = ($kind * self::DS_GRADE_COUNT * self::DS_PAGE_SIZE)
                    + ($grade * self::DS_PAGE_SIZE);
                $grades[] = [
                    'id' => (string) $grade,
                    'key' => self::DS_GRADE_KEYS[$grade],
                    'cells' => self::gridCells($dsByPos, $base, self::DS_COLUMNS, self::DS_ROWS),
                ];
            }

            $kinds[] = [
                'id' => (string) $kind,
                'key' => self::DS_KIND_KEYS[$kind],
                'grades' => $grades,
            ];
        }

        return [
            'columns' => self::DS_COLUMNS,
            'rows' => self::DS_ROWS,
            'decks' => $decks,
            'kinds' => $kinds,
        ];
    }

    /**
     * @param array<int, array{x: int, y: int, w: int, h: int, key: string}> $slots
     * @param list<array<string, mixed>> $items
     * @return array{width: int, height: int, slots: list<array<string, mixed>>, has_item: bool}
     */
    private static function paperdoll(array $slots, array $items, int $width, int $height): array
    {
        $byPos = self::indexByPos($items);
        $out = [];
        $hasItem = false;

        foreach ($slots as $pos => $slot) {
            $item = $byPos[$pos] ?? null;

            if ($item !== null) {
                $hasItem = true;
            }

            $out[] = [
                'pos' => $pos,
                'x' => $slot['x'],
                'y' => $slot['y'],
                'w' => $slot['w'],
                'h' => $slot['h'],
                'key' => $slot['key'],
                'item' => $item,
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'slots' => $out,
            'has_item' => $hasItem,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $placed
     * @return list<array<string, mixed>>
     */
    private static function unplaced(array $items, array $placed): array
    {
        $known = array_fill_keys($placed, true);
        $extra = [];

        foreach ($items as $item) {
            if (!isset($known[(int) $item['pos']])) {
                $extra[] = $item;
            }
        }

        return $extra;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{columns: int, rows: int, pages: list<array{id: string, label: string, cells: list<array<string, mixed>>}>}
     */
    private static function pagedGrid(array $items, int $columns, int $rows, int $minPages): array
    {
        $pageSize = $columns * $rows;
        $byPos = self::indexByPos($items);
        $maxEnd = -1;

        foreach ($items as $item) {
            $size = self::cellSize($item, $rows);
            $maxEnd = max($maxEnd, (int) $item['pos'] + $size - 1);
        }

        $needed = $maxEnd >= 0 ? (int) ceil(($maxEnd + 1) / $pageSize) : 0;
        $pages = max($minPages, $needed);

        $out = [];

        for ($page = 0; $page < $pages; $page++) {
            $out[] = [
                'id' => (string) $page,
                'label' => self::pageLabel($page),
                'cells' => self::gridCells($byPos, $page * $pageSize, $columns, $rows),
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'pages' => $out,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $byPos
     * @return list<array<string, mixed>>
     */
    private static function gridCells(array $byPos, int $basePos, int $columns, int $rows): array
    {
        $pageSize = $columns * $rows;
        $occupied = [];
        $cells = [];

        for ($i = 0; $i < $pageSize; $i++) {
            if (isset($occupied[$i])) {
                continue;
            }

            $pos = $basePos + $i;
            $item = $byPos[$pos] ?? null;
            $row = intdiv($i, $columns);
            $span = $item !== null ? min(self::cellSize($item, $rows), $rows - $row) : 1;

            for ($step = 1; $step < $span; $step++) {
                $occupied[$i + ($step * $columns)] = true;
            }

            $cells[] = [
                'pos' => $pos,
                'col' => ($i % $columns) + 1,
                'row' => $row + 1,
                'span' => $span,
                'item' => $item,
            ];
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function indexByPos(array $items): array
    {
        $byPos = [];

        foreach ($items as $item) {
            $pos = (int) $item['pos'];

            if (!isset($byPos[$pos])) {
                $byPos[$pos] = $item;
            }
        }

        return $byPos;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function cellSize(array $item, int $max): int
    {
        return max(1, min($max, (int) ($item['size'] ?? 1)));
    }

    private static function pageLabel(int $index): string
    {
        return self::PAGE_LABELS[$index] ?? (string) ($index + 1);
    }
}
