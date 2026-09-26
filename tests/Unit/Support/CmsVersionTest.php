<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\CmsVersion;
use PHPUnit\Framework\TestCase;

final class CmsVersionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/mt2cms-version-' . uniqid('', true);
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $path = $this->tmpDir . '/VERSION';
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testReadsValidVersionFile(): void
    {
        file_put_contents($this->tmpDir . '/VERSION', "1.0.0-beta.1\n");

        self::assertSame('1.0.0-beta.1', CmsVersion::read($this->tmpDir));
    }

    public function testMissingFileReturnsDev(): void
    {
        self::assertSame('dev', CmsVersion::read($this->tmpDir));
    }

    public function testEmptyFileReturnsDev(): void
    {
        file_put_contents($this->tmpDir . '/VERSION', "   \n");

        self::assertSame('dev', CmsVersion::read($this->tmpDir));
    }

    public function testInvalidCharactersReturnDev(): void
    {
        file_put_contents($this->tmpDir . '/VERSION', "1.0.0<script>");

        self::assertSame('dev', CmsVersion::read($this->tmpDir));
    }

    public function testControlCharactersReturnDev(): void
    {
        file_put_contents($this->tmpDir . '/VERSION', "1.0.0\x00evil");

        self::assertSame('dev', CmsVersion::read($this->tmpDir));
    }

    public function testOversizedVersionReturnsDev(): void
    {
        file_put_contents($this->tmpDir . '/VERSION', str_repeat('a', 65));

        self::assertSame('dev', CmsVersion::read($this->tmpDir));
    }

    public function testEmptyBaseDirReturnsDev(): void
    {
        self::assertSame('dev', CmsVersion::read(''));
    }
}
