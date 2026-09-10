<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class AdminSectionCatalog
{
    /** @var list<string> */
    public const SUPER_ONLY = [
        'admins',
        'roles',
        'audit-log',
    ];

    /** @var list<string> */
    private const GAME_SECTIONS = [
        'shops',
        'refine',
        'drops',
        'items',
        'mobs',
    ];

    /**
     * @return list<string>
     */
    public static function allIds(): array
    {
        $ids = [];

        foreach (self::grouped() as $group) {
            foreach ($group['children'] as $child) {
                $ids[] = (string) $child['id'];
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function assignableIds(): array
    {
        return array_values(array_filter(
            self::allIds(),
            static fn (string $id): bool => !in_array($id, self::SUPER_ONLY, true),
        ));
    }

    /**
     * @return list<string>
     */
    public static function logSectionIds(): array
    {
        $ids = ['log-' . LogCatalog::CONNECTIONS_ID];

        foreach (LogCatalog::all() as $log) {
            $ids[] = 'log-' . (string) $log['id'];
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function defaultSupportSections(): array
    {
        return array_values(array_unique(array_merge(
            [
                'dashboard',
                'accounts',
                'characters',
                'guilds',
                'awards',
                'tickets',
            ],
            self::logSectionIds(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function defaultContentSections(): array
    {
        return [
            'dashboard',
            'news',
            'news-comments',
            'news-settings',
            'item-shop',
            'item-shop-categories',
            'item-shop-orders',
            'tickets',
            'registration',
            'themes',
            'locale',
        ];
    }

    /**
     * @return list<array{id: string, label: string, children: list<array{id: string, label: string}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (AdminSections::all() as $group) {
            $children = [];

            foreach ($group['children'] as $child) {
                $children[] = [
                    'id' => (string) $child['id'],
                    'label' => (string) $child['label'],
                ];
            }

            $groups[] = [
                'id' => (string) $group['id'],
                'label' => (string) $group['label'],
                'children' => $children,
            ];
        }

        $groups[] = [
            'id' => 'game-data',
            'label' => 'admin.nav.game_data',
            'children' => [
                ['id' => 'shops', 'label' => 'admin.nav.shops'],
                ['id' => 'refine', 'label' => 'admin.nav.refine'],
                ['id' => 'drops', 'label' => 'admin.nav.drops'],
                ['id' => 'items', 'label' => 'admin.nav.proto_items'],
                ['id' => 'mobs', 'label' => 'admin.nav.proto_mobs'],
            ],
        ];

        return $groups;
    }

    public static function isSuperOnly(string $sectionId): bool
    {
        return in_array($sectionId, self::SUPER_ONLY, true);
    }
}
