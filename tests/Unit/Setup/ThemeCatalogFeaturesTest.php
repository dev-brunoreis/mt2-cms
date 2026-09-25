<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Setup;

use Mt2Cms\Setup\ThemeCatalog;
use PHPUnit\Framework\TestCase;

final class ThemeCatalogFeaturesTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/mt2cms-themes-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/default', 0777, true);
        mkdir($this->tmp . '/plain', 0777, true);
        mkdir($this->tmp . '/child', 0777, true);
        mkdir($this->tmp . '/optout', 0777, true);

        file_put_contents($this->tmp . '/default/theme.json', json_encode([
            'name' => 'default',
            'parent' => null,
            'features' => ['layout_columns' => true],
        ]));
        file_put_contents($this->tmp . '/plain/theme.json', json_encode([
            'name' => 'plain',
            'parent' => null,
        ]));
        file_put_contents($this->tmp . '/child/theme.json', json_encode([
            'name' => 'child',
            'parent' => 'default',
        ]));
        file_put_contents($this->tmp . '/optout/theme.json', json_encode([
            'name' => 'optout',
            'parent' => 'default',
            'features' => ['layout_columns' => false],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->tmp);
    }

    public function testDefaultDeclaresLayoutColumns(): void
    {
        $catalog = new ThemeCatalog($this->tmp);

        self::assertTrue($catalog->supportsFeature('default', 'layout_columns'));
        self::assertFalse($catalog->supportsFeature('plain', 'layout_columns'));
    }

    public function testChildInheritsLayoutColumnsFromParent(): void
    {
        $catalog = new ThemeCatalog($this->tmp);

        self::assertTrue($catalog->supportsFeature('child', 'layout_columns'));
    }

    public function testChildCanOptOutOfInheritedFeature(): void
    {
        $catalog = new ThemeCatalog($this->tmp);

        self::assertFalse($catalog->supportsFeature('optout', 'layout_columns'));
    }

    public function testMetaRejectsPathTraversalName(): void
    {
        $outside = dirname($this->tmp) . '/evil-theme-' . bin2hex(random_bytes(4));
        mkdir($outside, 0777, true);
        file_put_contents($outside . '/theme.json', json_encode(['name' => 'evil'], JSON_THROW_ON_ERROR));

        try {
            $catalog = new ThemeCatalog($this->tmp);
            $meta = $catalog->meta('../' . basename($outside));

            self::assertFalse($catalog->isValid('../' . basename($outside)));
            self::assertSame(['name' => '../' . basename($outside), 'parent' => null], $meta);
        } finally {
            unlink($outside . '/theme.json');
            rmdir($outside);
        }
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
