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
        return ['logs'];
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
                'logs',
            ],
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
            'banners',
            'store',
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

        return $groups;
    }

    public static function isSuperOnly(string $sectionId): bool
    {
        return in_array($sectionId, self::SUPER_ONLY, true);
    }
}
