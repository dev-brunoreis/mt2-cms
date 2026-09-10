<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\Locales;
use PHPUnit\Framework\TestCase;

final class LocalesRedirectTest extends TestCase
{
    private Locales $locales;

    protected function setUp(): void
    {
        $this->locales = new Locales(BASE_DIR . '/lang');
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
}
