<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Integration\Http;

use Mt2Cms\Tests\Integration\Support\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Live-stack smoke: requires docker compose (or equivalent) on MT2CMS_BASE_URL.
 * Skips when the stack is not reachable so unit CI stays green without MySQL.
 */
final class PublicSmokeTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = HttpClient::baseUrl();
        if (!HttpClient::isReachable($this->baseUrl)) {
            self::markTestSkipped(
                'Stack not reachable at ' . $this->baseUrl
                . ' (start docker compose or set MT2CMS_BASE_URL)'
            );
        }
    }

    public function testHealthReturnsOkWhenBothDatabasesRespond(): void
    {
        $response = HttpClient::get($this->baseUrl . '/health');

        self::assertSame(200, $response['status']);
        self::assertSame('ok', trim($response['body']));
    }

    public function testStatusReturnsOk(): void
    {
        $response = HttpClient::get($this->baseUrl . '/status');

        self::assertSame(200, $response['status']);
    }

    public function testHomeReturnsOk(): void
    {
        $response = HttpClient::get($this->baseUrl . '/');

        self::assertSame(200, $response['status']);
    }

    public function testLoginReturnsOk(): void
    {
        $response = HttpClient::get($this->baseUrl . '/login');

        self::assertSame(200, $response['status']);
    }

    public function testRobotsTxtReturnsOk(): void
    {
        $response = HttpClient::get($this->baseUrl . '/robots.txt');

        self::assertSame(200, $response['status']);
    }

    public function testSitemapXmlReturnsOk(): void
    {
        $response = HttpClient::get($this->baseUrl . '/sitemap.xml');

        self::assertSame(200, $response['status']);
        $looksXml = str_starts_with(ltrim($response['body']), '<?xml')
            || str_contains(strtolower($response['content_type']), 'xml');
        self::assertTrue($looksXml, 'sitemap should be XML (body or Content-Type)');
    }
}
