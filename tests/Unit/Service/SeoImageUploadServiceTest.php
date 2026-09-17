<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Service\SeoImageUploadService;
use PHPUnit\Framework\TestCase;

final class SeoImageUploadServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mt2-seo-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/uploads/seo', 0777, true);
        mkdir($this->root . '/uploads/news', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testIsStoredPathAcceptsSeoFiles(): void
    {
        $name = str_repeat('a', 32) . '.png';

        self::assertTrue(SeoImageUploadService::isStoredPath('/uploads/seo/' . $name));
        self::assertTrue(SeoImageUploadService::isStoredPath('/uploads/seo/' . str_repeat('b', 32) . '.jpg'));
        self::assertTrue(SeoImageUploadService::isStoredPath('/uploads/seo/' . str_repeat('c', 32) . '.webp'));
    }

    public function testIsStoredPathRejectsTraversalAndOtherDirs(): void
    {
        self::assertFalse(SeoImageUploadService::isStoredPath('/uploads/news/' . str_repeat('a', 32) . '.png'));
        self::assertFalse(SeoImageUploadService::isStoredPath('/uploads/seo/../news/secret.png'));
        self::assertFalse(SeoImageUploadService::isStoredPath('/uploads/seo/not-hex.png'));
        self::assertFalse(SeoImageUploadService::isStoredPath('/uploads/seo/' . str_repeat('a', 32) . '.gif'));
        self::assertFalse(SeoImageUploadService::isStoredPath('/uploads/seo/' . str_repeat('a', 32) . '.svg'));
    }

    public function testDeleteRemovesStoredSeoImage(): void
    {
        $name = str_repeat('b', 32) . '.jpg';
        $path = $this->root . '/uploads/seo/' . $name;
        file_put_contents($path, 'og');

        (new SeoImageUploadService($this->root))->delete('/uploads/seo/' . $name);

        self::assertFileDoesNotExist($path);
    }

    public function testDeleteIgnoresFilesOutsideSeoDir(): void
    {
        $victim = $this->root . '/uploads/news/secret.jpg';
        file_put_contents($victim, 'keep');

        (new SeoImageUploadService($this->root))->delete('/uploads/news/secret.jpg');
        (new SeoImageUploadService($this->root))->delete('/uploads/seo/../../../uploads/news/secret.jpg');

        self::assertFileExists($victim);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;

            if (is_dir($full)) {
                $this->removeDir($full);
                continue;
            }

            unlink($full);
        }

        rmdir($path);
    }
}
