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

    public static function storeCategoryNew(?int $parentId = null): string
    {
        $path = '/admin/store?tab=categories&new=1';

        if ($parentId !== null && $parentId > 0) {
            $path .= '&parent_id=' . $parentId;
        }

        return $path;
    }

    public static function storeCategoryEdit(int $id, ?string $panel = null): string
    {
        $path = '/admin/store?tab=categories&id=' . $id;

        if ($panel !== null && $panel !== '') {
            $path .= '&panel=' . rawurlencode($panel);
        }

        return $path;
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

    private static function withTab(string $base, ?string $tab): string
    {
        if ($tab === null || $tab === '') {
            return $base;
        }

        return $base . '?tab=' . rawurlencode($tab);
    }
}
