<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class AdminSections
{
    /**
     * @return list<array{id: string, label: string, collapsible?: bool, children: list<array{id: string, path: string, label: string}>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'overview',
                'label' => 'admin.nav.overview',
                'children' => [
                    [
                        'id' => 'dashboard',
                        'path' => '/admin',
                        'label' => 'admin.nav.dashboard',
                    ],
                ],
            ],
            [
                'id' => 'game',
                'label' => 'admin.nav.game',
                'children' => [
                    [
                        'id' => 'accounts',
                        'path' => '/admin/accounts',
                        'label' => 'admin.nav.accounts',
                    ],
                    [
                        'id' => 'characters',
                        'path' => '/admin/characters',
                        'label' => 'admin.nav.characters',
                    ],
                    [
                        'id' => 'guilds',
                        'path' => '/admin/guilds',
                        'label' => 'admin.nav.guilds',
                    ],
                    [
                        'id' => 'awards',
                        'path' => '/admin/awards',
                        'label' => 'admin.nav.awards',
                    ],
                ],
            ],
            [
                'id' => 'content',
                'label' => 'admin.nav.content',
                'children' => [
                    [
                        'id' => 'items',
                        'path' => '/admin/items',
                        'label' => 'admin.nav.items',
                    ],
                    [
                        'id' => 'mobs',
                        'path' => '/admin/mobs',
                        'label' => 'admin.nav.mobs',
                    ],
                    [
                        'id' => 'shops',
                        'path' => '/admin/shops',
                        'label' => 'admin.nav.shops',
                    ],
                    [
                        'id' => 'refine',
                        'path' => '/admin/refine',
                        'label' => 'admin.nav.refine',
                    ],
                    [
                        'id' => 'drops',
                        'path' => '/admin/drops',
                        'label' => 'admin.nav.drops',
                    ],
                ],
            ],
            [
                'id' => 'logs',
                'label' => 'admin.nav.logs.group',
                'collapsible' => true,
                'children' => LogCatalog::navItems(),
            ],
            [
                'id' => 'configuration',
                'label' => 'admin.nav.configuration',
                'children' => [
                    [
                        'id' => 'registration',
                        'path' => '/admin/registration',
                        'label' => 'admin.nav.registration',
                    ],
                    [
                        'id' => 'themes',
                        'path' => '/admin/themes',
                        'label' => 'admin.nav.themes',
                    ],
                    [
                        'id' => 'locale',
                        'path' => '/admin/locale',
                        'label' => 'admin.nav.locale',
                    ],
                    [
                        'id' => 'gms',
                        'path' => '/admin/gms',
                        'label' => 'admin.nav.gms',
                    ],
                ],
            ],
        ];
    }

    public static function firstPath(): string
    {
        foreach (self::all() as $group) {
            $first = $group['children'][0]['path'] ?? null;

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return '/admin/registration';
    }

    public static function groupForSection(string $sectionId): ?string
    {
        foreach (self::all() as $group) {
            foreach ($group['children'] as $child) {
                if ($child['id'] === $sectionId) {
                    return $group['id'];
                }
            }
        }

        return null;
    }
}
