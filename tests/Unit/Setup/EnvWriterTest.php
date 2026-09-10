<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Setup;

use Mt2Cms\Setup\EnvWriter;
use PHPUnit\Framework\TestCase;

final class EnvWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mt2cms-envwriter-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $path = $this->tempDir . '/.env';

        if (is_file($path)) {
            @unlink($path);
        }

        @rmdir($this->tempDir);
    }

    public function testRejectsNewlineInValue(): void
    {
        $writer = new EnvWriter();

        $this->expectException(\InvalidArgumentException::class);

        $writer->write(['DB_PASSWORD' => "secret\ninjection"], $this->tempDir . '/.env');
    }

    public function testWritesFileWithRestrictedPermissions(): void
    {
        $path = $this->tempDir . '/.env';
        $writer = new EnvWriter();
        $writer->write(['DB_PASSWORD' => 'secret123'], $path);

        self::assertFileExists($path);
        self::assertSame(0640, fileperms($path) & 0777);
        self::assertStringContainsString('DB_PASSWORD=secret123', (string) file_get_contents($path));
    }
}
