<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin;

use Mt2Cms\Admin\AdminResourceCatalog;
use Mt2Cms\Admin\LogCatalog;
use PHPUnit\Framework\TestCase;

final class AdminResourceCatalogTest extends TestCase
{
    public function testResourceIdsFollowConvention(): void
    {
        self::assertContains('game/accounts/view', AdminResourceCatalog::allResourceIds());
        self::assertContains('store/products/create', AdminResourceCatalog::allResourceIds());
        self::assertContains('content/news/posts/mass', AdminResourceCatalog::allResourceIds());
        self::assertContains('store/products/view', AdminResourceCatalog::allResourceIds());
    }

    public function testPrefixInheritance(): void
    {
        self::assertTrue(AdminResourceCatalog::resourceMatches(
            'content/news/posts/delete',
            'content/news/posts',
        ));
        self::assertFalse(AdminResourceCatalog::resourceMatches(
            'content/news/comments/view',
            'content/news/posts',
        ));
    }

    public function testLegacyNewsExpandsToNewsResources(): void
    {
        $resources = AdminResourceCatalog::resourcesForLegacySection('news');

        self::assertContains('content/news/posts/view', $resources);
        self::assertContains('content/news/comments/view', $resources);
        self::assertContains('content/news/settings/edit', $resources);
    }

    public function testGameDataLegacyResources(): void
    {
        $resources = AdminResourceCatalog::resourcesForLegacySection('shops');

        self::assertContains('game-data/shops/view', $resources);
        self::assertContains('game-data/shops/mass', $resources);
    }

    public function testLogTabsGenerateViewResources(): void
    {
        foreach (LogCatalog::tabIds() as $tabId) {
            self::assertContains(
                'logs/' . $tabId . '/view',
                AdminResourceCatalog::allResourceIds(),
            );
        }
    }

    public function testPathForStoreOrders(): void
    {
        self::assertSame(
            '/admin/store?tab=orders',
            AdminResourceCatalog::pathForResource('store/orders/view'),
        );
    }
}
