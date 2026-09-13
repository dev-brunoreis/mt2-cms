<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Service\SeoService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class SeoServiceTest extends TestCase
{
    public function testDefaultTitleAndDescriptionFallbacks(): void
    {
        $seo = $this->service();
        $page = $seo->forPage([], 'Ranking', '/ranking');

        self::assertSame('Ranking — My Server', $page['title']);
        self::assertSame('Default description', $page['description']);
        self::assertSame('https://example.com/ranking', $page['canonical']);
        self::assertSame('https://example.com/uploads/seo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg', $page['image']);
        self::assertSame('index, follow', $page['robots']);
        self::assertSame('website', $page['type']);
    }

    public function testOverrideTitleIsUsedAsFullDocumentTitle(): void
    {
        $page = $this->service()->forPage(['title' => 'Custom tab'], 'News title', '/news/3');

        self::assertSame('Custom tab', $page['title']);
    }

    public function testSettingBeatsExcerptAndOverrideImageWins(): void
    {
        $page = $this->service()->forPage([
            'excerpt_html' => '<p>Hello <strong>world</strong> from the article body.</p>',
            'image' => '/uploads/news/2026/01/cover.jpg',
        ], 'Post', '/news/1');

        self::assertSame('Default description', $page['description']);
        self::assertSame('https://example.com/uploads/news/2026/01/cover.jpg', $page['image']);
    }

    public function testExcerptUsedWhenSettingIsEmpty(): void
    {
        $page = $this->service(description: '')->forPage([
            'excerpt_html' => '<p>Hello <strong>world</strong> from the article body.</p>',
        ], 'Post', '/news/1');

        self::assertSame('Hello world from the article body.', $page['description']);
    }

    public function testCanonicalStripsQueryString(): void
    {
        $page = $this->service()->forPage([], 'Home', '/news?page=2');

        self::assertSame('https://example.com/news', $page['canonical']);
    }

    public function testPrivatePathsAndIndexOffSendNoindex(): void
    {
        $indexed = $this->service()->forPage([], 'Account', '/account/password');
        self::assertSame('noindex, nofollow', $indexed['robots']);
        self::assertSame([], $indexed['json_ld']);

        $off = $this->service(indexEnabled: false)->forPage([], 'Home', '/');
        self::assertSame('noindex, nofollow', $off['robots']);
    }

    public function testAbsoluteHttpImageIsKept(): void
    {
        $page = $this->service()->forPage([
            'image' => 'https://cdn.example.com/og.png',
        ], 'Home', '/');

        self::assertSame('https://cdn.example.com/og.png', $page['image']);
    }

    public function testJsonLdEscapesScriptBreakout(): void
    {
        $encoded = SeoService::encodeJsonLd([
            '@type' => 'NewsArticle',
            'headline' => '</script><script>alert(1)</script>',
        ]);

        self::assertStringNotContainsString('</script>', $encoded);
        self::assertStringContainsString('\\u003C', $encoded);
    }

    public function testArticleJsonLdIncludesDates(): void
    {
        $page = $this->service()->forPage([
            'type' => 'article',
            'headline' => 'Launch',
            'published_at' => '2026-01-02 15:00:00',
            'modified_at' => '2026-01-03 10:00:00',
        ], 'Launch', '/news/9');

        self::assertCount(2, $page['json_ld']);
        self::assertSame('Organization', $page['json_ld'][0]['@type']);
        self::assertSame('NewsArticle', $page['json_ld'][1]['@type']);
        self::assertSame('Launch', $page['json_ld'][1]['headline']);
        self::assertNotSame('', $page['json_ld'][1]['datePublished'] ?? '');
    }

    public function testRobotsDisallowAllWhenIndexDisabled(): void
    {
        $txt = $this->service(indexEnabled: false)->robotsTxt();

        self::assertStringContainsString("User-agent: *\nDisallow: /\n", $txt);
        self::assertStringNotContainsString('Sitemap:', $txt);
    }

    public function testRobotsAllowsSiteAndListsPrivatePaths(): void
    {
        $txt = $this->service()->robotsTxt();

        self::assertStringContainsString('Allow: /', $txt);
        self::assertStringContainsString('Disallow: /account', $txt);
        self::assertStringContainsString('Disallow: /admin', $txt);
        self::assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $txt);
    }

    public function testSitemapIncludesPublishedItemsAndOmitsDrafts(): void
    {
        $xml = $this->service()->sitemapXml(
            [
                ['id' => 4, 'updated_at' => '2026-03-01 12:00:00', 'published_at' => '2026-03-01 12:00:00'],
            ],
            [
                ['id' => 8, 'updated_at' => '2026-04-01 08:00:00', 'published_at' => '2026-04-01 08:00:00'],
            ],
        );

        self::assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.com/news/4</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.com/events/8</loc>', $xml);
        self::assertStringContainsString('<lastmod>2026-03-01</lastmod>', $xml);
        self::assertStringNotContainsString('/news/99', $xml);
        self::assertStringNotContainsString('/player/', $xml);
    }

    private function service(bool $indexEnabled = true, string $description = 'Default description'): SeoService
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('siteTitle')->willReturn('My Server');
        $settings->method('siteUrl')->willReturn('https://example.com');
        $settings->method('siteLogo')->willReturn('/uploads/logo/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png');
        $settings->method('seoDescription')->willReturn($description);
        $settings->method('seoOgImage')->willReturn('/uploads/seo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $settings->method('seoIndexEnabled')->willReturn($indexEnabled);
        $settings->method('seoGoogleVerification')->willReturn('google-token');
        $settings->method('seoBingVerification')->willReturn('');
        $settings->method('seoTwitterSite')->willReturn('@myserver');

        return new SeoService($settings, new HtmlSanitizer());
    }
}
