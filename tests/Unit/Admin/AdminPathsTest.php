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
        self::assertSame('/admin/store?tab=categories&new=1', AdminPaths::storeCategoryNew());
        self::assertSame('/admin/store?tab=categories&new=1&parent_id=4', AdminPaths::storeCategoryNew(4));
        self::assertSame('/admin/store?tab=categories&id=12&panel=products', AdminPaths::storeCategoryEdit(12, 'products'));
        self::assertSame('/admin/system/roles', AdminPaths::systemRoles());
    }

    public function testAdminSectionsUsesCanonicalPaths(): void
    {
        self::assertSame('/admin/logs?tab=connections', AdminSections::sectionPath('logs'));
        self::assertSame('/admin/store', AdminSections::sectionPath('store'));
        self::assertSame('/admin/game-data/shops', AdminSections::sectionPath('shops'));
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
