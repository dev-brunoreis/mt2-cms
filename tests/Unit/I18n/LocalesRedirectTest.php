<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\Locales;
use PHPUnit\Framework\TestCase;

final class LocalesRedirectTest extends TestCase
{
    private string $langDir;

    private Locales $locales;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/mt2-locales-redirect-' . bin2hex(random_bytes(4));
        mkdir($this->langDir, 0777, true);
        file_put_contents(
            $this->langDir . '/en.json',
            json_encode(['locale' => ['name' => 'English']], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->langDir . '/xx.json',
            json_encode(['locale' => ['name' => 'Extra']], JSON_THROW_ON_ERROR),
        );

        $this->locales = new Locales($this->langDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->langDir . '/en.json');
        @unlink($this->langDir . '/xx.json');
        @rmdir($this->langDir);
    }

    public function testAllowsInternalPath(): void
    {
        self::assertSame('/account', $this->locales->safeRedirect('/account'));
    }

    public function testRejectsProtocolRelative(): void
    {
        self::assertSame('/', $this->locales->safeRedirect('//evil.example'));
    }

    public function testRejectsCarriageReturn(): void
    {
        self::assertSame('/', $this->locales->safeRedirect("/login\r\nLocation: http://evil"));
    }

    public function testRejectsEmptyAndExternal(): void
    {
        self::assertSame('/', $this->locales->safeRedirect(null));
        self::assertSame('/', $this->locales->safeRedirect('https://evil.example'));
    }

    public function testResolvePrefersCookieOverDefault(): void
    {
        $prev = $_COOKIE;
        $_COOKIE[Locales::COOKIE] = 'xx';

        try {
            self::assertSame('xx', $this->locales->resolve('en'));
        } finally {
            $_COOKIE = $prev;
        }
    }

    public function testResolveFallsBackToDefaultWhenCookieMissing(): void
    {
        $prev = $_COOKIE;
        unset($_COOKIE[Locales::COOKIE]);

        try {
            self::assertSame('xx', $this->locales->resolve('xx'));
        } finally {
            $_COOKIE = $prev;
        }
    }

    public function testResolveFallsBackToEnglishWhenDefaultUnsupported(): void
    {
        $prev = $_COOKIE;
        unset($_COOKIE[Locales::COOKIE]);

        try {
            self::assertSame('en', $this->locales->resolve('missing'));
        } finally {
            $_COOKIE = $prev;
        }
    }
}
