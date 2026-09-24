<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Theme;

use Mt2Cms\Theme\ThemeAssetFile;
use PHPUnit\Framework\TestCase;

final class ThemeAssetFileTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/mt2cms-theme-asset-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/default/assets/css', 0777, true);
        file_put_contents($this->tmp . '/default/assets/css/theme.css', 'body{}');
        file_put_contents($this->tmp . '/default/assets/secret.php', '<?php echo 1;');
    }

    protected function tearDown(): void
    {
        $files = [
            $this->tmp . '/default/assets/secret.php',
            $this->tmp . '/default/assets/css/theme.css',
        ];

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->tmp . '/default/assets/css')) {
            rmdir($this->tmp . '/default/assets/css');
        }

        if (is_dir($this->tmp . '/default/assets')) {
            rmdir($this->tmp . '/default/assets');
        }

        if (is_dir($this->tmp . '/default')) {
            rmdir($this->tmp . '/default');
        }

        if (is_dir($this->tmp)) {
            rmdir($this->tmp);
        }
    }

    public function testParsesThemeAssetUri(): void
    {
        $parsed = ThemeAssetFile::parseUri('/theme-assets/default/css/theme.css?v=1');

        self::assertSame(['theme' => 'default', 'asset' => 'css/theme.css'], $parsed);
    }

    public function testRejectsUnsafeRelativePaths(): void
    {
        self::assertFalse(ThemeAssetFile::isSafeRelativePath('../secret.php'));
        self::assertFalse(ThemeAssetFile::isSafeRelativePath('css/theme.php'));
        self::assertFalse(ThemeAssetFile::isSafeRelativePath('css/theme.css.bak'));
        self::assertTrue(ThemeAssetFile::isSafeRelativePath('css/theme.css'));
    }

    public function testResolvesExistingSafeAsset(): void
    {
        $path = ThemeAssetFile::absolutePath($this->tmp, 'default', 'css/theme.css');

        self::assertNotNull($path);
        self::assertSame('body{}', file_get_contents((string) $path));
        self::assertSame('text/css; charset=UTF-8', ThemeAssetFile::mimeType('css/theme.css'));
    }

    public function testRejectsPhpAndMissingFiles(): void
    {
        self::assertNull(ThemeAssetFile::absolutePath($this->tmp, 'default', 'secret.php'));
        self::assertNull(ThemeAssetFile::absolutePath($this->tmp, 'default', 'css/missing.css'));
        self::assertNull(ThemeAssetFile::absolutePath($this->tmp, '../default', 'css/theme.css'));
    }
}
