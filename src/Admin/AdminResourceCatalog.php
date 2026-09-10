<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

/**
 * Hierarchical ACL resources ({area}/{module}/[{entity}/]{action}) for admin roles.
 */
final class AdminResourceCatalog
{
    /** @var list<string>|null */
    private static ?array $flatCache = null;

    /** @var array<string, list<string>>|null */
    private static ?array $legacyMapCache = null;

    /** @var list<string> */
    private const CRUD = ['view', 'create', 'edit', 'delete', 'mass'];

    /**
     * Full tree for role assignment UI (includes navHidden game-data).
     *
     * @return list<array<string, mixed>>
     */
    public static function tree(): array
    {
        return [
            self::group('admin.nav.overview', 'overview', [
                self::subgroup('admin.nav.dashboard', 'dashboard', [
                    self::entity('admin.resources.stats', 'stats', ['view']),
                    self::entity('admin.resources.players', 'players', ['view']),
                ]),
            ]),
            self::group('admin.nav.game', 'game', [
                self::flatModule('admin.nav.accounts', 'accounts', ['view', 'create', 'edit', 'delete', 'mass', 'block']),
                self::flatModule('admin.nav.characters', 'characters', ['view']),
                self::flatModule('admin.nav.guilds', 'guilds', ['view', 'edit', 'kick', 'delete_comment', 'dissolve']),
                self::flatModule('admin.nav.awards', 'awards', ['view', 'create', 'delete', 'mass']),
            ]),
            self::group('admin.nav.content', 'content', [
                self::subgroup('admin.nav.news', 'news', [
                    self::entity('admin.resources.posts', 'posts', self::CRUD),
                    self::entity('admin.resources.comments', 'comments', self::CRUD),
                    self::entity('admin.resources.news_settings', 'settings', ['view', 'edit']),
                ]),
                self::flatModule('admin.nav.tickets', 'tickets', ['view', 'reply', 'close', 'reopen', 'mass']),
            ]),
            self::group('admin.nav.store', 'store', [
                self::flatModule('admin.resources.categories', 'categories', ['view', 'create', 'edit', 'delete', 'move']),
                self::flatModule('admin.resources.products', 'products', ['create', 'edit', 'delete', 'mass']),
                self::flatModule('admin.resources.orders', 'orders', ['view']),
            ]),
            self::group('admin.nav.game_data', 'game-data', self::gameDataModules(), navHidden: true),
            self::group('admin.nav.logs.group', 'logs', self::logModules()),
            self::group('admin.nav.settings', 'settings', [
                self::flatModule('admin.nav.registration', 'registration', ['view', 'edit']),
                self::flatModule('admin.nav.themes', 'themes', ['view', 'edit']),
                self::flatModule('admin.nav.locale', 'locale', ['view', 'edit']),
                self::flatModule('admin.nav.security', 'security', ['view', 'edit']),
            ]),
        ];
    }

    /**
     * @return list<string>
     */
    public static function allResourceIds(): array
    {
        if (self::$flatCache !== null) {
            return self::$flatCache;
        }

        $ids = [];
        self::collectIds(self::tree(), $ids);
        sort($ids);
        self::$flatCache = $ids;

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function assignableIds(): array
    {
        return self::allResourceIds();
    }

    public static function isKnown(string $resourceId): bool
    {
        return in_array($resourceId, self::allResourceIds(), true);
    }

    public static function isSuperOnly(string $resourceId): bool
    {
        return str_starts_with($resourceId, 'system/');
    }

    /**
     * @return list<string>
     */
    public static function navHiddenSectionIds(): array
    {
        return ['shops', 'refine', 'drops', 'items', 'mobs', 'gms'];
    }

    /**
     * @return list<string>
     */
    public static function resourcesForLegacySection(string $sectionId): array
    {
        if (isset(self::legacyMap()[$sectionId])) {
            return self::legacyMap()[$sectionId];
        }

        $prefix = self::sectionResourcePrefix($sectionId);

        if ($prefix === null) {
            return [];
        }

        return self::resourcesUnderPrefix($prefix);
    }

    public static function navResourceForSection(string $sectionId): ?string
    {
        return match ($sectionId) {
            'dashboard' => 'overview/dashboard/stats/view',
            'store' => 'store/categories/view',
            'logs' => self::firstLogViewResource(),
            'news' => 'content/news/posts/view',
            default => self::firstViewUnderPrefix(self::sectionResourcePrefix($sectionId)),
        };
    }

    public static function pathForResource(string $resourceId): ?string
    {
        $sectionId = self::sectionIdForResource($resourceId);

        if ($sectionId === null) {
            return null;
        }

        if ($sectionId === 'dashboard') {
            if (str_contains($resourceId, '/players/')) {
                return AdminPaths::gameAccounts();
            }

            return AdminPaths::DASHBOARD;
        }

        if ($sectionId === 'store') {
            if (str_starts_with($resourceId, 'store/orders/')) {
                return AdminPaths::store('orders');
            }

            return AdminPaths::store();
        }

        if ($sectionId === 'logs') {
            foreach (LogCatalog::tabIds() as $tabId) {
                if (str_starts_with($resourceId, 'logs/' . $tabId . '/')) {
                    return AdminPaths::logs($tabId);
                }
            }

            return AdminPaths::logs();
        }

        if ($sectionId === 'news') {
            return AdminPaths::contentNews();
        }

        return AdminPaths::sectionPath($sectionId);
    }

    public static function sectionIdForResource(string $resourceId): ?string
    {
        if (str_starts_with($resourceId, 'overview/dashboard/')) {
            return 'dashboard';
        }

        if (str_starts_with($resourceId, 'game/accounts/')) {
            return 'accounts';
        }

        if (str_starts_with($resourceId, 'game/characters/')) {
            return 'characters';
        }

        if (str_starts_with($resourceId, 'game/guilds/')) {
            return 'guilds';
        }

        if (str_starts_with($resourceId, 'game/awards/')) {
            return 'awards';
        }

        if (str_starts_with($resourceId, 'content/news/')) {
            return 'news';
        }

        if (str_starts_with($resourceId, 'content/tickets/')) {
            return 'tickets';
        }

        if (str_starts_with($resourceId, 'store/')) {
            return 'store';
        }

        if (str_starts_with($resourceId, 'logs/')) {
            return 'logs';
        }

        if (str_starts_with($resourceId, 'settings/registration/')) {
            return 'registration';
        }

        if (str_starts_with($resourceId, 'settings/themes/')) {
            return 'themes';
        }

        if (str_starts_with($resourceId, 'settings/locale/')) {
            return 'locale';
        }

        if (str_starts_with($resourceId, 'settings/security/')) {
            return 'security';
        }

        foreach (self::navHiddenSectionIds() as $sectionId) {
            if (str_starts_with($resourceId, 'game-data/' . $sectionId . '/')) {
                return $sectionId;
            }
        }

        return null;
    }

    public static function sectionResourcePrefix(string $sectionId): ?string
    {
        return match ($sectionId) {
            'dashboard' => 'overview/dashboard',
            'accounts' => 'game/accounts',
            'characters' => 'game/characters',
            'guilds' => 'game/guilds',
            'awards' => 'game/awards',
            'news' => 'content/news',
            'tickets' => 'content/tickets',
            'store' => 'store',
            'logs' => 'logs',
            'registration' => 'settings/registration',
            'themes' => 'settings/themes',
            'locale' => 'settings/locale',
            'security' => 'settings/security',
            'shops', 'refine', 'drops', 'items', 'mobs', 'gms' => 'game-data/' . $sectionId,
            default => null,
        };
    }

    public static function resourceMatches(string $resourceId, string $granted): bool
    {
        if ($resourceId === $granted) {
            return true;
        }

        return str_starts_with($resourceId, $granted . '/');
    }

    /**
     * @param list<string> $granted
     */
    public static function isAllowedResource(string $resourceId, array $granted): bool
    {
        foreach ($granted as $entry) {
            if (self::resourceMatches($resourceId, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $granted
     */
    public static function hasAnyResourceForSection(string $sectionId, array $granted): bool
    {
        if (AdminSectionCatalog::isSuperOnly($sectionId)) {
            return false;
        }

        $prefix = self::sectionResourcePrefix($sectionId);

        if ($prefix === null) {
            return false;
        }

        foreach ($granted as $entry) {
            if ($entry === $prefix || str_starts_with($entry, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function gameDataModules(): array
    {
        $modules = [];

        foreach (self::navHiddenSectionIds() as $sectionId) {
            $labelKey = match ($sectionId) {
                'items' => 'admin.nav.proto_items',
                'mobs' => 'admin.nav.proto_mobs',
                default => 'admin.nav.' . $sectionId,
            };
            $modules[] = self::flatModule($labelKey, $sectionId, self::CRUD);
        }

        return $modules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function logModules(): array
    {
        $modules = [];

        foreach (LogCatalog::tabIds() as $tabId) {
            $modules[] = self::flatModule('admin.logs.tabs.' . $tabId, $tabId, ['view']);
        }

        return $modules;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function group(string $labelKey, string $areaId, array $children, bool $navHidden = false): array
    {
        $node = [
            'id' => $areaId,
            'label' => $labelKey,
            'children' => $children,
        ];

        if ($navHidden) {
            $node['navHidden'] = true;
        }

        return $node;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function subgroup(string $labelKey, string $moduleId, array $children): array
    {
        return [
            'id' => $moduleId,
            'label' => $labelKey,
            'children' => $children,
        ];
    }

    /**
     * @param list<string> $actions
     * @return array<string, mixed>
     */
    private static function flatModule(string $labelKey, string $moduleId, array $actions): array
    {
        return [
            'id' => $moduleId,
            'label' => $labelKey,
            'flatActions' => true,
            'children' => self::actionLeaves($actions),
        ];
    }

    /**
     * @param list<string> $actions
     * @return array<string, mixed>
     */
    private static function entity(string $labelKey, string $entityId, array $actions): array
    {
        return [
            'id' => $entityId,
            'label' => $labelKey,
            'children' => self::actionLeaves($actions),
        ];
    }

    /**
     * @param list<string> $actions
     * @return list<array<string, mixed>>
     */
    private static function actionLeaves(array $actions): array
    {
        $leaves = [];

        foreach ($actions as $action) {
            $leaves[] = [
                'id' => $action,
                'label' => 'admin.resources.actions.' . $action,
                'leaf' => true,
            ];
        }

        return $leaves;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<string> $ids
     */
    private static function collectIds(array $nodes, array &$ids, string $prefix = ''): void
    {
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            $path = $prefix === '' ? $id : $prefix . '/' . $id;
            $children = $node['children'] ?? [];

            if (($node['leaf'] ?? false) === true) {
                $ids[] = $path;

                continue;
            }

            if (($node['flatActions'] ?? false) === true) {
                foreach ($children as $action) {
                    $ids[] = $path . '/' . (string) ($action['id'] ?? '');
                }

                continue;
            }

            if ($children === []) {
                $ids[] = $path;
            }

            self::collectIds($children, $ids, $path);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private static function legacyMap(): array
    {
        if (self::$legacyMapCache !== null) {
            return self::$legacyMapCache;
        }

        $map = [
            'logs' => self::resourcesUnderPrefix('logs'),
            'store' => self::resourcesUnderPrefix('store'),
            'news' => self::resourcesUnderPrefix('content/news'),
        ];

        foreach (self::navHiddenSectionIds() as $sectionId) {
            $map[$sectionId] = self::resourcesUnderPrefix('game-data/' . $sectionId);
        }

        self::$legacyMapCache = $map;

        return $map;
    }

    /**
     * @return list<string>
     */
    private static function resourcesUnderPrefix(string $prefix): array
    {
        $out = [];

        foreach (self::allResourceIds() as $id) {
            if ($id === $prefix || str_starts_with($id, $prefix . '/')) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private static function firstLogViewResource(): ?string
    {
        $tabs = LogCatalog::tabIds();

        return $tabs !== [] ? 'logs/' . $tabs[0] . '/view' : null;
    }

    private static function firstViewUnderPrefix(?string $prefix): ?string
    {
        if ($prefix === null) {
            return null;
        }

        foreach (self::resourcesUnderPrefix($prefix) as $id) {
            if (str_ends_with($id, '/view')) {
                return $id;
            }
        }

        $under = self::resourcesUnderPrefix($prefix);

        return $under[0] ?? null;
    }
}
