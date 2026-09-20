<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Setup\ThemeCatalog;
use PHPUnit\Framework\TestCase;

final class SettingsLayoutTest extends TestCase
{
    /** @var array<string, string> */
    private array $store = [];

    /** @var array<string, true> */
    private array $layoutThemes = ['default' => true];

    protected function setUp(): void
    {
        $this->store = ['active_theme' => 'default'];
        $this->layoutThemes = ['default' => true];
    }

    public function testLayoutDefaultsToThreeColumnsLeft(): void
    {
        $service = $this->service();

        self::assertSame(3, $service->layoutColumns());
        self::assertSame('left', $service->layoutSidebar());
    }

    public function testSetLayoutPersistsTwoColumnsRight(): void
    {
        $service = $this->service();
        $service->setLayout(2, 'right');

        self::assertSame(2, $service->layoutColumns());
        self::assertSame('right', $service->layoutSidebar());
        self::assertSame('2', $this->store['layout_columns']);
        self::assertSame('right', $this->store['layout_sidebar']);
    }

    public function testLayoutIgnoredWhenThemeLacksFeature(): void
    {
        $this->layoutThemes = [];
        $this->store = [
            'active_theme' => 'plain',
            'layout_columns' => '2',
            'layout_sidebar' => 'right',
        ];
        $service = $this->service(['plain']);

        self::assertFalse($service->themeSupportsLayoutColumns());
        self::assertSame(3, $service->layoutColumns());
        self::assertSame('left', $service->layoutSidebar());
    }

    public function testSetLayoutRejectsUnsupportedTheme(): void
    {
        $this->store = ['active_theme' => 'plain'];
        $this->layoutThemes = [];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.themes.layout_unsupported');
        $this->service(['plain'])->setLayout(2, 'left');
    }

    public function testSetLayoutRejectsInvalidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.themes.layout_columns_invalid');
        $this->service()->setLayout(4, 'left');
    }

    public function testSetLayoutRejectsInvalidSidebar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.themes.layout_sidebar_invalid');
        $this->service()->setLayout(2, 'center');
    }

    public function testSetActiveThemeRejectsUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.active_theme_invalid');
        $this->service()->setActiveTheme('missing-theme');
    }

    public function testSetActiveThemePersistsValidPublicTheme(): void
    {
        $service = $this->service();
        $service->setActiveTheme('default');

        self::assertSame('default', $this->store['active_theme']);
        self::assertSame('default', $service->activeTheme());
    }

    /**
     * @param list<string> $publicThemes
     */
    private function service(array $publicThemes = ['default']): SettingsService
    {
        $repo = $this->createMock(SettingsRepository::class);
        $repo->method('get')->willReturnCallback(function (string $key, ?string $default = null): ?string {
            return $this->store[$key] ?? $default;
        });
        $repo->method('set')->willReturnCallback(function (string $key, string $value): void {
            $this->store[$key] = $value;
        });

        $themes = $this->createMock(ThemeCatalog::class);
        $themes->method('isValid')->willReturnCallback(static fn (string $t): bool => in_array($t, $publicThemes, true));
        $themes->method('isPublic')->willReturnCallback(static fn (string $t): bool => in_array($t, $publicThemes, true));
        $themes->method('supportsFeature')->willReturnCallback(function (string $t, string $feature): bool {
            return $feature === 'layout_columns' && isset($this->layoutThemes[$t]);
        });

        return new SettingsService($repo, $themes);
    }
}
