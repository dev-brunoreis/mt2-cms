<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testStripsScriptTags(): void
    {
        $result = $this->sanitizer->sanitize('<p>ok</p><script>alert(1)</script>');

        self::assertStringNotContainsString('script', $result);
        self::assertStringContainsString('<p>ok</p>', $result);
    }

    public function testRemovesJavascriptHref(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">x</a>');

        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringNotContainsString('<a', $result);
    }

    public function testRemovesProtocolRelativeHref(): void
    {
        $result = $this->sanitizer->sanitize('<a href="//evil.example">x</a>');

        self::assertStringNotContainsString('//evil', $result);
    }

    public function testRemovesEventHandlers(): void
    {
        $result = $this->sanitizer->sanitize('<p onclick="alert(1)">x</p>');

        self::assertStringNotContainsString('onclick', $result);
        self::assertStringContainsString('<p>', $result);
    }

    public function testRejectsImageOutsideNewsUploads(): void
    {
        $result = $this->sanitizer->sanitize('<img src="/uploads/evil.png" alt="x">');

        self::assertStringNotContainsString('<img', $result);
    }

    public function testAllowsNewsUploadImage(): void
    {
        $result = $this->sanitizer->sanitize('<img src="/uploads/news/2026/03/abc123.jpg" alt="x">');

        self::assertStringContainsString('/uploads/news/2026/03/abc123.jpg', $result);
    }

    public function testUnwrapsDisallowedTags(): void
    {
        $result = $this->sanitizer->sanitize('<div><strong>keep</strong></div>');

        self::assertStringNotContainsString('<div', $result);
        self::assertStringContainsString('<strong>keep</strong>', $result);
    }

    public function testTicketHtmlEscapesPlainText(): void
    {
        $result = $this->sanitizer->ticketHtml("hello & 'test'");

        self::assertStringContainsString('&amp;', $result);
        self::assertStringContainsString('&#039;', $result);
    }
}
