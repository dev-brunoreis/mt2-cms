<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Support\HtmlSanitizer;

class SeoService
{
    public const PRIVATE_PATHS = [
        '/login',
        '/register',
        '/account',
        '/forgot-password',
        '/reset-password',
        '/verify-email',
        '/donate/return',
        '/donate/cancel',
        '/donate/pay',
        '/admin',
    ];

    /** @var list<string> */
    private const SITEMAP_STATIC_PATHS = [
        '/',
        '/news',
        '/events',
        '/ranking',
        '/downloads',
        '/status',
        '/shop',
        '/donate',
    ];

    public function __construct(
        private SettingsService $settings,
        private HtmlSanitizer $sanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{
     *   title: string,
     *   description: string,
     *   canonical: string,
     *   image: string,
     *   robots: string,
     *   type: string,
     *   google_verification: string,
     *   bing_verification: string,
     *   twitter_site: string,
     *   json_ld: list<array<string, mixed>>
     * }
     */
    public function forPage(array $overrides, string $pageTitle, string $requestUri = '/'): array
    {
        $path = $this->pathOnly($requestUri);
        $brand = $this->brand();
        $overrideTitle = trim((string) ($overrides['title'] ?? ''));
        $title = $overrideTitle !== ''
            ? $overrideTitle
            : $this->defaultTitle($pageTitle, $brand);

        $description = trim((string) ($overrides['description'] ?? ''));

        if ($description === '') {
            $description = $this->settings->seoDescription();
        }

        if ($description === '') {
            $description = trim((string) ($overrides['fallback_description'] ?? ''));
        }

        if ($description === '') {
            $excerptHtml = trim((string) ($overrides['excerpt_html'] ?? ''));

            if ($excerptHtml !== '') {
                $description = $this->sanitizer->excerpt($excerptHtml, 160);
            }
        }

        $image = $this->absoluteUrl((string) ($overrides['image'] ?? ''));

        if ($image === '') {
            $image = $this->absoluteUrl($this->settings->seoOgImage());
        }

        if ($image === '') {
            $image = $this->absoluteUrl($this->settings->siteLogo());
        }

        $noindex = !empty($overrides['noindex'])
            || !$this->settings->seoIndexEnabled()
            || $this->isPrivatePath($path);

        $type = (string) ($overrides['type'] ?? 'website');

        if (!in_array($type, ['website', 'article', 'event'], true)) {
            $type = 'website';
        }

        $canonical = $this->settings->siteUrl() . ($path === '/' ? '/' : $path);
        $robots = $noindex ? 'noindex, nofollow' : 'index, follow';

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'robots' => $robots,
            'type' => $type,
            'google_verification' => $this->settings->seoGoogleVerification(),
            'bing_verification' => $this->settings->seoBingVerification(),
            'twitter_site' => $this->settings->seoTwitterSite(),
            'json_ld' => $noindex ? [] : $this->jsonLd(
                $overrides,
                $title,
                $description,
                $canonical,
                $image,
                $brand,
                $type,
                $pageTitle,
            ),
        ];
    }

    public function robotsTxt(): string
    {
        $lines = ['User-agent: *'];

        if (!$this->settings->seoIndexEnabled()) {
            $lines[] = 'Disallow: /';

            return implode("\n", $lines) . "\n";
        }

        $lines[] = 'Allow: /';

        foreach (self::PRIVATE_PATHS as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        $lines[] = 'Sitemap: ' . $this->settings->siteUrl() . '/sitemap.xml';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<array{id: int|string, updated_at?: mixed, published_at?: mixed}> $news
     * @param list<array{id: int|string, updated_at?: mixed, published_at?: mixed}> $events
     */
    public function sitemapXml(array $news, array $events): string
    {
        $urls = [];

        foreach (self::SITEMAP_STATIC_PATHS as $path) {
            $urls[] = ['loc' => $this->settings->siteUrl() . ($path === '/' ? '/' : $path), 'lastmod' => null];
        }

        foreach ($news as $row) {
            $urls[] = [
                'loc' => $this->settings->siteUrl() . '/news/' . (int) $row['id'],
                'lastmod' => $this->sitemapDate($row['updated_at'] ?? $row['published_at'] ?? null),
            ];
        }

        foreach ($events as $row) {
            $urls[] = [
                'loc' => $this->settings->siteUrl() . '/events/' . (int) $row['id'],
                'lastmod' => $this->sitemapDate($row['updated_at'] ?? $row['published_at'] ?? null),
            ];
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $this->xmlEscape((string) $url['loc']) . '</loc>';

            if (is_string($url['lastmod']) && $url['lastmod'] !== '') {
                $xml[] = '    <lastmod>' . $this->xmlEscape($url['lastmod']) . '</lastmod>';
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    public static function encodeJsonLd(mixed $value): string
    {
        if (!is_array($value)) {
            return '{}';
        }

        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<array<string, mixed>>
     */
    private function jsonLd(
        array $overrides,
        string $title,
        string $description,
        string $canonical,
        string $image,
        string $brand,
        string $type,
        string $pageTitle,
    ): array {
        $graphs = [];
        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $brand,
            'url' => $this->settings->siteUrl(),
        ];
        $logo = $this->absoluteUrl($this->settings->siteLogo());

        if ($logo !== '') {
            $organization['logo'] = $logo;
        }

        $graphs[] = $organization;

        $headline = trim((string) ($overrides['headline'] ?? ''));

        if ($headline === '') {
            $headline = $pageTitle !== '' ? $pageTitle : $title;
        }

        if ($type === 'article') {
            $article = [
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $headline,
                'mainEntityOfPage' => $canonical,
            ];

            if ($description !== '') {
                $article['description'] = $description;
            }

            if ($image !== '') {
                $article['image'] = $image;
            }

            $published = $this->isoDate($overrides['published_at'] ?? null);

            if ($published !== '') {
                $article['datePublished'] = $published;
            }

            $modified = $this->isoDate($overrides['modified_at'] ?? null);

            if ($modified !== '') {
                $article['dateModified'] = $modified;
            }

            $graphs[] = $article;
        }

        if ($type === 'event') {
            $event = [
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $headline,
                'url' => $canonical,
                'eventStatus' => 'https://schema.org/EventScheduled',
            ];

            if ($description !== '') {
                $event['description'] = $description;
            }

            if ($image !== '') {
                $event['image'] = $image;
            }

            $start = $this->isoDate($overrides['starts_at'] ?? $overrides['published_at'] ?? null);

            if ($start !== '') {
                $event['startDate'] = $start;
            }

            $end = $this->isoDate($overrides['ends_at'] ?? null);

            if ($end !== '') {
                $event['endDate'] = $end;
            }

            $graphs[] = $event;
        }

        return $graphs;
    }

    private function defaultTitle(string $pageTitle, string $brand): string
    {
        $pageTitle = trim($pageTitle);

        if ($pageTitle === '' || $pageTitle === $brand) {
            return $brand;
        }

        return $pageTitle . ' — ' . $brand;
    }

    private function brand(): string
    {
        $title = $this->settings->siteTitle();

        return $title !== '' ? $title : 'Mt2 CMS';
    }

    private function pathOnly(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        if (str_contains($path, "\r") || str_contains($path, "\n")) {
            return '/';
        }

        return $path;
    }

    public function isPrivatePath(string $path): bool
    {
        foreach (self::PRIVATE_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '';
        }

        return $this->settings->siteUrl() . $path;
    }

    private function isoDate(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return '';
        }

        return date('c', $timestamp);
    }

    private function sitemapDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
