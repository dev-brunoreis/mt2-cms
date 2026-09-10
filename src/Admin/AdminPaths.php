<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class AdminPaths
{
    public const DASHBOARD = '/admin';
    public const LOGIN = '/admin/login';

    // Game
    public static function gameAccounts(): string
    {
        return '/admin/game/accounts';
    }

    public static function gameCharacters(): string
    {
        return '/admin/game/characters';
    }

    public static function gameOwnedItem(int $id): string
    {
        return '/admin/game/owned-items/' . $id;
    }

    public static function gameGuilds(): string
    {
        return '/admin/game/guilds';
    }

    public static function gameAwards(): string
    {
        return '/admin/game/awards';
    }

    // Content
    public static function contentNews(?string $tab = null): string
    {
        return self::withTab('/admin/content/news', $tab);
    }

    public static function contentNewsPost(int $id): string
    {
        return '/admin/content/news/posts/' . $id;
    }

    public static function contentNewsPostNew(): string
    {
        return '/admin/content/news/posts/new';
    }

    public static function contentTickets(): string
    {
        return '/admin/content/tickets';
    }

    // Store
    public static function store(?string $tab = null): string
    {
        return self::withTab('/admin/store', $tab);
    }

    public static function storeProducts(): string
    {
        return '/admin/store/products';
    }

    public static function storeProduct(int $id): string
    {
        return '/admin/store/products/' . $id;
    }

    public static function storeProductNew(): string
    {
        return '/admin/store/products/new';
    }

    public static function storeCategories(): string
    {
        return '/admin/store/categories';
    }

    public static function storeCategory(int $id): string
    {
        return '/admin/store/categories/' . $id;
    }

    public static function storeCategoryNew(): string
    {
        return '/admin/store/categories/new';
    }

    public static function storeOrders(): string
    {
        return '/admin/store/orders';
    }

    // Game data
    public static function gameDataShops(): string
    {
        return '/admin/game-data/shops';
    }

    public static function gameDataRefine(): string
    {
        return '/admin/game-data/refine';
    }

    public static function gameDataDrops(): string
    {
        return '/admin/game-data/drops';
    }

    public static function gameDataItems(): string
    {
        return '/admin/game-data/items';
    }

    public static function gameDataMobs(): string
    {
        return '/admin/game-data/mobs';
    }

    public static function gameDataGms(): string
    {
        return '/admin/game-data/gms';
    }

    // Logs
    public static function logs(?string $tab = null): string
    {
        return self::withTab('/admin/logs', $tab ?? LogCatalog::CONNECTIONS_ID);
    }

    // System
    public static function systemAdmins(): string
    {
        return '/admin/system/admins';
    }

    public static function systemRoles(): string
    {
        return '/admin/system/roles';
    }

    public static function systemAuditLog(): string
    {
        return '/admin/system/audit-log';
    }

    // Settings
    public static function settingsRegistration(): string
    {
        return '/admin/settings/registration';
    }

    public static function settingsThemes(): string
    {
        return '/admin/settings/themes';
    }

    public static function settingsLocale(): string
    {
        return '/admin/settings/locale';
    }

    public static function sectionPath(string $sectionId): ?string
    {
        return match ($sectionId) {
            'dashboard' => self::DASHBOARD,
            'accounts' => self::gameAccounts(),
            'characters' => self::gameCharacters(),
            'guilds' => self::gameGuilds(),
            'awards' => self::gameAwards(),
            'news' => self::contentNews(),
            'tickets' => self::contentTickets(),
            'store' => self::store(),
            'shops' => self::gameDataShops(),
            'refine' => self::gameDataRefine(),
            'drops' => self::gameDataDrops(),
            'items' => self::gameDataItems(),
            'mobs' => self::gameDataMobs(),
            'gms' => self::gameDataGms(),
            'logs' => self::logs(),
            'admins' => self::systemAdmins(),
            'roles' => self::systemRoles(),
            'audit-log' => self::systemAuditLog(),
            'registration' => self::settingsRegistration(),
            'themes' => self::settingsThemes(),
            'locale' => self::settingsLocale(),
            default => null,
        };
    }

    /**
     * Resolve a legacy admin path (no query) to its canonical replacement, or null.
     */
    public static function resolveLegacyRedirect(string $path): ?string
    {
        $path = rtrim($path, '/') ?: '/';

        if ($path === '/admin/logs') {
            return self::logs();
        }

        if (preg_match('#^/admin/logs/([a-z0-9_]+)$#', $path, $m)) {
            return self::logs($m[1]);
        }

        if (preg_match('#^/admin/(items|mobs)(/.*)?$#', $path, $m)) {
            $suffix = $m[2] ?? '';

            return '/admin/game-data/' . $m[1] . $suffix;
        }

        $static = self::legacyStaticMap();

        if (isset($static[$path])) {
            return $static[$path];
        }

        if (preg_match('#^/admin/news/(\d+)$#', $path, $m)) {
            return self::contentNewsPost((int) $m[1]);
        }

        if (preg_match('#^/admin/item-shop/(\d+)$#', $path, $m)) {
            return self::storeProduct((int) $m[1]);
        }

        if (preg_match('#^/admin/item-shop/categories/(\d+)(/.*)?$#', $path, $m)) {
            $suffix = $m[2] ?? '';

            return self::storeCategory((int) $m[1]) . $suffix;
        }

        if (preg_match('#^/admin/(accounts|characters|guilds|shops|refine|gms|admins)/(\d+)$#', $path, $m)) {
            return self::legacyStaticMap()['/admin/' . $m[1]] . '/' . $m[2];
        }

        if (preg_match('#^/admin/tickets/(\d+)(/.*)?$#', $path, $m)) {
            return self::contentTickets() . '/' . $m[1] . ($m[2] ?? '');
        }

        if (preg_match('#^/admin/drops/mob/(\d+)$#', $path, $m)) {
            return self::gameDataDrops() . '/mob/' . $m[1];
        }

        if (preg_match('#^/admin/roles/([a-z0-9-]+)$#', $path, $m)) {
            return self::systemRoles() . '/' . $m[1];
        }

        if (preg_match('#^/admin/owned-items/(\d+)$#', $path, $m)) {
            return self::gameOwnedItem((int) $m[1]);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function legacyStaticMap(): array
    {
        return [
            '/admin/registration' => self::settingsRegistration(),
            '/admin/themes' => self::settingsThemes(),
            '/admin/locale' => self::settingsLocale(),
            '/admin/admins' => self::systemAdmins(),
            '/admin/roles' => self::systemRoles(),
            '/admin/permissions' => self::systemRoles(),
            '/admin/audit-log' => self::systemAuditLog(),
            '/admin/accounts' => self::gameAccounts(),
            '/admin/accounts/new' => self::gameAccounts() . '/new',
            '/admin/characters' => self::gameCharacters(),
            '/admin/guilds' => self::gameGuilds(),
            '/admin/awards' => self::gameAwards(),
            '/admin/awards/new' => self::gameAwards() . '/new',
            '/admin/news' => self::contentNews('posts'),
            '/admin/news/new' => self::contentNewsPostNew(),
            '/admin/news/comments' => self::contentNews('comments'),
            '/admin/news/settings' => self::contentNews('settings'),
            '/admin/tickets' => self::contentTickets(),
            '/admin/item-shop' => self::store('products'),
            '/admin/item-shop/new' => self::storeProductNew(),
            '/admin/item-shop/orders' => self::storeOrders(),
            '/admin/item-shop/categories' => self::storeCategories(),
            '/admin/item-shop/categories/new' => self::storeCategoryNew(),
            '/admin/shops' => self::gameDataShops(),
            '/admin/shops/new' => self::gameDataShops() . '/new',
            '/admin/refine' => self::gameDataRefine(),
            '/admin/refine/new' => self::gameDataRefine() . '/new',
            '/admin/drops' => self::gameDataDrops(),
            '/admin/drops/etc' => self::gameDataDrops() . '/etc',
            '/admin/drops/common' => self::gameDataDrops() . '/common',
            '/admin/gms' => self::gameDataGms(),
            '/admin/gms/new' => self::gameDataGms() . '/new',
        ];
    }

    private static function withTab(string $base, ?string $tab): string
    {
        if ($tab === null || $tab === '') {
            return $base;
        }

        return $base . '?tab=' . rawurlencode($tab);
    }
}
