<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class AdminSections
{
    /**
     * @return list<array{id: string, label: string, collapsible?: bool, pinned?: bool, children: list<array{id: string, path: string, label: string}>}>
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
                        'path' => AdminPaths::DASHBOARD,
                        'label' => 'admin.nav.dashboard',
                    ],
                    [
                        'id' => 'population',
                        'path' => AdminPaths::population(),
                        'label' => 'admin.nav.population',
                    ],
                ],
            ],
            [
                'id' => 'game',
                'label' => 'admin.nav.game',
                'children' => [
                    [
                        'id' => 'accounts',
                        'path' => AdminPaths::gameAccounts(),
                        'label' => 'admin.nav.accounts',
                    ],
                    [
                        'id' => 'characters',
                        'path' => AdminPaths::gameCharacters(),
                        'label' => 'admin.nav.characters',
                    ],
                    [
                        'id' => 'guilds',
                        'path' => AdminPaths::gameGuilds(),
                        'label' => 'admin.nav.guilds',
                    ],
                    [
                        'id' => 'awards',
                        'path' => AdminPaths::gameAwards(),
                        'label' => 'admin.nav.awards',
                    ],
                    [
                        'id' => 'bans',
                        'path' => AdminPaths::gameBans(),
                        'label' => 'admin.nav.bans',
                    ],
                    [
                        'id' => 'referrals',
                        'path' => AdminPaths::gameReferrals(),
                        'label' => 'admin.nav.referrals',
                    ],
                    [
                        'id' => 'economy',
                        'path' => AdminPaths::gameEconomy(),
                        'label' => 'admin.nav.economy',
                    ],
                ],
            ],
            [
                'id' => 'content',
                'label' => 'admin.nav.content',
                'children' => [
                    [
                        'id' => 'news',
                        'path' => AdminPaths::contentNews(),
                        'label' => 'admin.nav.news',
                    ],
                    [
                        'id' => 'tickets',
                        'path' => AdminPaths::contentTickets(),
                        'label' => 'admin.nav.tickets',
                    ],
                    [
                        'id' => 'downloads',
                        'path' => AdminPaths::contentDownloads(),
                        'label' => 'admin.nav.downloads',
                    ],
                    [
                        'id' => 'banners',
                        'path' => AdminPaths::contentBanners(),
                        'label' => 'admin.nav.banners',
                    ],
                    [
                        'id' => 'events',
                        'path' => AdminPaths::contentEvents(),
                        'label' => 'admin.nav.events',
                    ],
                ],
            ],
            [
                'id' => 'store',
                'label' => 'admin.nav.store',
                'children' => [
                    [
                        'id' => 'store',
                        'path' => AdminPaths::store(),
                        'label' => 'admin.nav.item_shop',
                    ],
                    [
                        'id' => 'packages',
                        'path' => AdminPaths::storePackages(),
                        'label' => 'admin.nav.packages',
                    ],
                    [
                        'id' => 'payments',
                        'path' => AdminPaths::storePayments(),
                        'label' => 'admin.nav.payments',
                    ],
                ],
            ],
            [
                'id' => 'game-data',
                'label' => 'admin.nav.game_data',
                'children' => [
                    [
                        'id' => 'shops',
                        'path' => AdminPaths::gameDataShops(),
                        'label' => 'admin.nav.shops',
                    ],
                    [
                        'id' => 'refine',
                        'path' => AdminPaths::gameDataRefine(),
                        'label' => 'admin.nav.refine',
                    ],
                    [
                        'id' => 'drops',
                        'path' => AdminPaths::gameDataDrops(),
                        'label' => 'admin.nav.drops',
                    ],
                    [
                        'id' => 'items',
                        'path' => AdminPaths::gameDataItems(),
                        'label' => 'admin.nav.proto_items',
                    ],
                    [
                        'id' => 'mobs',
                        'path' => AdminPaths::gameDataMobs(),
                        'label' => 'admin.nav.proto_mobs',
                    ],
                    [
                        'id' => 'gms',
                        'path' => AdminPaths::gameDataGms(),
                        'label' => 'admin.nav.gms',
                    ],
                ],
            ],
            [
                'id' => 'logs',
                'label' => 'admin.nav.logs.group',
                'children' => [
                    [
                        'id' => 'logs',
                        'path' => AdminPaths::logs(),
                        'label' => 'admin.nav.logs.group',
                    ],
                ],
            ],
            [
                'id' => 'system',
                'label' => 'admin.nav.system',
                'children' => [
                    [
                        'id' => 'admins',
                        'path' => AdminPaths::systemAdmins(),
                        'label' => 'admin.nav.admins',
                    ],
                    [
                        'id' => 'roles',
                        'path' => AdminPaths::systemRoles(),
                        'label' => 'admin.nav.roles',
                    ],
                    [
                        'id' => 'audit-log',
                        'path' => AdminPaths::systemAuditLog(),
                        'label' => 'admin.nav.audit_log',
                    ],
                ],
            ],
            [
                'id' => 'settings',
                'label' => 'admin.nav.configuration',
                'pinned' => true,
                'children' => [
                    [
                        'id' => 'settings',
                        'path' => AdminPaths::settings(),
                        'label' => 'admin.nav.configuration',
                    ],
                ],
            ],
        ];
    }

    /**
     * First child of a pinned sidebar group (ACL-filtered tree).
     *
     * @param list<array<string, mixed>> $sections
     * @return array{id: string, path: string, label: string}|null
     */
    public static function pinnedNavItem(array $sections): ?array
    {
        foreach ($sections as $group) {
            if (($group['pinned'] ?? false) !== true) {
                continue;
            }

            $child = $group['children'][0] ?? null;

            if (!is_array($child)) {
                return null;
            }

            $id = (string) ($child['id'] ?? '');
            $path = (string) ($child['path'] ?? '');
            $label = (string) ($child['label'] ?? '');

            if ($id === '' || $path === '' || $label === '') {
                return null;
            }

            return [
                'id' => $id,
                'path' => $path,
                'label' => $label,
            ];
        }

        return null;
    }

    public static function firstPath(): string
    {
        foreach (self::navSectionIds() as $sectionId) {
            $path = self::sectionPath($sectionId);

            if ($path !== null) {
                return $path;
            }
        }

        return AdminPaths::settings();
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

        return $ids;
    }

    public static function sectionPath(string $sectionId): ?string
    {
        return AdminPaths::sectionPath($sectionId);
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

    /**
     * @return list<array{label: string, href: string|null, translate: bool}>
     */
    public static function breadcrumbs(string $sectionId, string $currentTitle, string $requestUri): array
    {
        $path = self::requestPath($requestUri);
        $onIndex = self::isSectionIndex($sectionId, $path);
        $crumbs = [
            ['label' => 'admin.title', 'href' => AdminPaths::DASHBOARD, 'translate' => true],
        ];

        $group = self::groupById(self::groupForSection($sectionId));

        if ($group !== null && count($group['children']) > 1) {
            $firstPath = (string) ($group['children'][0]['path'] ?? '');
            $crumbs[] = [
                'label' => (string) $group['label'],
                'href' => $firstPath !== '' ? $firstPath : null,
                'translate' => true,
            ];
        }

        $section = self::sectionById($sectionId);

        if ($section !== null) {
            $sectionHref = (string) ($section['path'] ?? '');
            $crumbs[] = [
                'label' => (string) $section['label'],
                'href' => ($onIndex || $sectionHref === '') ? null : $sectionHref,
                'translate' => true,
            ];
        }

        if (!$onIndex && $currentTitle !== '') {
            $crumbs[] = [
                'label' => $currentTitle,
                'href' => null,
                'translate' => false,
            ];
        } elseif ($section === null && $currentTitle !== '') {
            $crumbs[] = [
                'label' => $currentTitle,
                'href' => null,
                'translate' => false,
            ];
        }

        $last = array_key_last($crumbs);

        if ($last !== null) {
            $crumbs[$last]['href'] = null;
        }

        return array_values($crumbs);
    }

    public static function backHref(string $sectionId, string $requestUri, ?string $groupId = null): ?string
    {
        $path = self::requestPath($requestUri);

        if (self::isSectionIndex($sectionId, $path)) {
            return null;
        }

        $href = self::sectionPath($sectionId);

        if ($href !== null) {
            return $href;
        }

        $group = self::groupById($groupId ?? self::groupForSection($sectionId));
        $first = $group['children'][0]['path'] ?? null;

        return is_string($first) && $first !== '' ? $first : AdminPaths::DASHBOARD;
    }

    public static function isSectionIndex(string $sectionId, string $requestPath): bool
    {
        $href = self::sectionPath($sectionId);

        if ($href === null) {
            return false;
        }

        $sectionPath = parse_url($href, PHP_URL_PATH);

        return $requestPath === $sectionPath || $requestPath === (string) $href;
    }

    /**
     * @return array{id: string, label: string, children: list<array<string, mixed>>}|null
     */
    private static function groupById(?string $groupId): ?array
    {
        if ($groupId === null || $groupId === '') {
            return null;
        }

        foreach (self::all() as $group) {
            if ($group['id'] === $groupId) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, path: string, label: string}|null
     */
    private static function sectionById(string $sectionId): ?array
    {
        foreach (self::all() as $group) {
            foreach ($group['children'] as $child) {
                if ($child['id'] === $sectionId) {
                    return $child;
                }
            }
        }

        return null;
    }

    private static function requestPath(string $uri): string
    {
        if ($uri === '' || $uri[0] !== '/') {
            return '/';
        }

        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

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
        return self::navSectionIds();
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
        return [
            'dashboard',
            'accounts',
            'characters',
            'guilds',
            'awards',
            'tickets',
            'logs',
        ];
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

        foreach (self::all() as $group) {
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
