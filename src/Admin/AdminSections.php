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
                        'id' => 'news',
                        'path' => '/admin/news',
                        'label' => 'admin.nav.news',
                    ],
                    [
                        'id' => 'news-comments',
                        'path' => '/admin/news/comments',
                        'label' => 'admin.nav.news_comments',
                    ],
                    [
                        'id' => 'tickets',
                        'path' => '/admin/tickets',
                        'label' => 'admin.nav.tickets',
                    ],
                    [
                        'id' => 'item-shop',
                        'path' => '/admin/item-shop',
                        'label' => 'admin.nav.item_shop',
                    ],
                    [
                        'id' => 'item-shop-categories',
                        'path' => '/admin/item-shop/categories',
                        'label' => 'admin.nav.item_shop_categories',
                    ],
                    [
                        'id' => 'item-shop-orders',
                        'path' => '/admin/item-shop/orders',
                        'label' => 'admin.nav.item_shop_orders',
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
                'id' => 'system',
                'label' => 'admin.nav.system',
                'children' => [
                    [
                        'id' => 'admins',
                        'path' => '/admin/admins',
                        'label' => 'admin.nav.admins',
                    ],
                    [
                        'id' => 'roles',
                        'path' => '/admin/roles',
                        'label' => 'admin.nav.roles',
                    ],
                    [
                        'id' => 'audit-log',
                        'path' => '/admin/audit-log',
                        'label' => 'admin.nav.audit_log',
                    ],
                ],
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
                        'id' => 'news-settings',
                        'path' => '/admin/news/settings',
                        'label' => 'admin.nav.news_settings',
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
        foreach (self::navSectionIds() as $sectionId) {
            $path = self::sectionPath($sectionId);

            if ($path !== null) {
                return $path;
            }
        }

        return '/admin/registration';
    }

    /**
     * @return list<string>
     */
    public static function navSectionIds(): array
    {
        $ids = [];

        foreach (self::all() as $group) {
            foreach ($group['children'] as $child) {
                $ids[] = (string) $child['id'];
            }
        }

        foreach (['shops', 'refine', 'drops', 'items', 'mobs'] as $sectionId) {
            $ids[] = $sectionId;
        }

        return $ids;
    }

    public static function sectionPath(string $sectionId): ?string
    {
        foreach (self::all() as $group) {
            foreach ($group['children'] as $child) {
                if (($child['id'] ?? '') === $sectionId) {
                    return (string) ($child['path'] ?? '');
                }
            }
        }

        return match ($sectionId) {
            'shops' => '/admin/shops',
            'refine' => '/admin/refine',
            'drops' => '/admin/drops',
            'items' => '/admin/items',
            'mobs' => '/admin/mobs',
            default => null,
        };
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
