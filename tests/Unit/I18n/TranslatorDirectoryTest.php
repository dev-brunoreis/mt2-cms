<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorDirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mt2-i18n-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/en', 0777, true);
        file_put_contents($this->root . '/en/locale.json', json_encode(['name' => 'English'], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/en/nav.json', json_encode(['home' => 'Home'], JSON_THROW_ON_ERROR));
        file_put_contents($this->root . '/en/app.json', json_encode(['name' => 'CMS'], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->rm($this->root);
    }

    public function testLoadsNamespacedDirectory(): void
    {
        $t = new Translator($this->root, 'en', 'en');

        self::assertSame('Home', $t->get('nav.home'));
        self::assertSame('CMS', $t->get('app.name'));
        self::assertSame('English', $t->get('locale.name'));
    }

    private function rm(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->rm($full) : unlink($full);
        }

        rmdir($path);
    }
}
