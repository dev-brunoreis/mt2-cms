<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\AdminSections;
use Mt2Cms\Admin\LogCatalog;
use PHPUnit\Framework\TestCase;

final class AdminPathsTest extends TestCase
{
    public function testSectionPaths(): void
    {
        self::assertSame('/admin/logs?tab=connections', AdminPaths::logs());
        self::assertSame('/admin/logs?tab=hack_log', AdminPaths::logs('hack_log'));
        self::assertSame('/admin/content/news?tab=comments', AdminPaths::contentNews('comments'));
        self::assertSame('/admin/store/products', AdminPaths::storeProducts());
        self::assertSame('/admin/system/roles', AdminPaths::systemRoles());
    }

    public function testAdminSectionsUsesCanonicalPaths(): void
    {
        self::assertSame('/admin/logs?tab=connections', AdminSections::sectionPath('logs'));
        self::assertSame('/admin/store', AdminSections::sectionPath('store'));
        self::assertSame('/admin/game-data/shops', AdminSections::sectionPath('shops'));
    }

    public function testLegacyRedirects(): void
    {
        self::assertSame(
            '/admin/logs?tab=command_log',
            AdminPaths::resolveLegacyRedirect('/admin/logs/command_log'),
        );
        self::assertSame(
            '/admin/content/news?tab=comments',
            AdminPaths::resolveLegacyRedirect('/admin/news/comments'),
        );
        self::assertSame(
            '/admin/store?tab=products',
            AdminPaths::resolveLegacyRedirect('/admin/item-shop'),
        );
        self::assertSame(
            '/admin/game-data/items/42',
            AdminPaths::resolveLegacyRedirect('/admin/items/42'),
        );
        self::assertSame(
            '/admin/content/news/posts/7',
            AdminPaths::resolveLegacyRedirect('/admin/news/7'),
        );
    }

    public function testLogCatalogHasGroupedTabsIncludingConnections(): void
    {
        $groups = LogCatalog::groupedTabs();
        $ids = [];

        foreach ($groups as $group) {
            foreach ($group['tabs'] as $tab) {
                $ids[] = $tab['id'];
            }
        }

        self::assertContains(LogCatalog::CONNECTIONS_ID, $ids);
        self::assertContains('hack_log', $ids);
        self::assertGreaterThan(20, count($ids));
    }
}
