<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Service\LogoUploadService;
use PHPUnit\Framework\TestCase;

final class LogoUploadServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mt2-logo-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/uploads/logo', 0777, true);
        mkdir($this->root . '/uploads/news', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testIsStoredPathAcceptsLogoFiles(): void
    {
        $name = str_repeat('a', 32) . '.png';

        self::assertTrue(LogoUploadService::isStoredPath('/uploads/logo/' . $name));
    }

    public function testIsStoredPathRejectsTraversalAndOtherDirs(): void
    {
        self::assertFalse(LogoUploadService::isStoredPath('/uploads/news/' . str_repeat('a', 32) . '.png'));
        self::assertFalse(LogoUploadService::isStoredPath('/uploads/logo/../news/secret.png'));
        self::assertFalse(LogoUploadService::isStoredPath('/uploads/logo/not-hex.png'));
        self::assertFalse(LogoUploadService::isStoredPath('/uploads/logo/' . str_repeat('a', 32) . '.svg'));
    }

    public function testDeleteRemovesStoredLogo(): void
    {
        $name = str_repeat('b', 32) . '.jpg';
        $path = $this->root . '/uploads/logo/' . $name;
        file_put_contents($path, 'logo');

        (new LogoUploadService($this->root))->delete('/uploads/logo/' . $name);

        self::assertFileDoesNotExist($path);
    }

    public function testDeleteIgnoresFilesOutsideLogoDir(): void
    {
        $victim = $this->root . '/uploads/news/secret.jpg';
        file_put_contents($victim, 'keep');

        (new LogoUploadService($this->root))->delete('/uploads/news/secret.jpg');
        (new LogoUploadService($this->root))->delete('/uploads/logo/../../../uploads/news/secret.jpg');

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
