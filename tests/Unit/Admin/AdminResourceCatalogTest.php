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
        self::assertContains('game/economy/view', AdminResourceCatalog::allResourceIds());
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

    public function testSettingsHubPaths(): void
    {
        self::assertSame('settings', AdminResourceCatalog::sectionIdForResource('settings/security/view'));
        self::assertSame('settings', AdminResourceCatalog::sectionIdForResource('content/banners/settings/edit'));
        self::assertSame('banners', AdminResourceCatalog::sectionIdForResource('content/banners/slides/view'));
        self::assertSame(
            '/admin/settings?tab=locale',
            AdminResourceCatalog::pathForResource('settings/locale/edit'),
        );
        self::assertSame(
            '/admin/settings?tab=payment-methods',
            AdminResourceCatalog::pathForResource('settings/payment-methods/view'),
        );
        self::assertContains('settings/payment-methods/edit', AdminResourceCatalog::allResourceIds());
        self::assertContains('settings/seo/view', AdminResourceCatalog::allResourceIds());
        self::assertSame(
            '/admin/settings?tab=seo',
            AdminResourceCatalog::pathForResource('settings/seo/edit'),
        );
        self::assertSame('settings', AdminResourceCatalog::sectionIdForResource('content/news/settings/edit'));
        self::assertSame('news', AdminResourceCatalog::sectionIdForResource('content/news/posts/view'));
        self::assertSame(
            '/admin/settings?tab=news',
            AdminResourceCatalog::pathForResource('content/news/settings/view'),
        );
        self::assertSame(
            '/admin/content/news',
            AdminResourceCatalog::pathForResource('content/news/posts/view'),
        );
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'settings',
            ['content/news/settings/view'],
        ));
        self::assertFalse(AdminResourceCatalog::hasAnyResourceForSection(
            'news',
            ['content/news/settings/view'],
        ));
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'news',
            ['content/news/posts/view'],
        ));
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'settings',
            ['content/news'],
        ));
        self::assertSame(
            '/admin/settings?tab=banners',
            AdminResourceCatalog::pathForResource('content/banners/settings/view'),
        );
        self::assertSame(
            '/admin/content/banners',
            AdminResourceCatalog::pathForResource('content/banners/slides/view'),
        );
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'settings',
            ['content/banners/settings/view'],
        ));
        self::assertFalse(AdminResourceCatalog::hasAnyResourceForSection(
            'banners',
            ['content/banners/settings/view'],
        ));
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'banners',
            ['content/banners'],
        ));
        self::assertTrue(AdminResourceCatalog::hasAnyResourceForSection(
            'settings',
            ['content/banners'],
        ));
    }
}
