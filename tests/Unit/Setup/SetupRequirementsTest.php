<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Setup;

use Mt2Cms\Setup\SetupRequirements;
use PHPUnit\Framework\TestCase;

final class SetupRequirementsTest extends TestCase
{
    private const IMG_JPG = 2;
    private const IMG_PNG = 4;
    private const IMG_WEBP = 32;

    public function testAllRequiredOkWhenEnvironmentIsHealthy(): void
    {
        $checker = $this->checker(
            phpVersion: '8.3.0',
            extensions: true,
            imageTypes: self::IMG_JPG | self::IMG_PNG | self::IMG_WEBP,
            writable: true,
            files: true,
            dirs: true,
        );

        self::assertTrue($checker->allRequiredOk());

        $ids = array_column($checker->checks(), 'id');
        self::assertContains('php_version', $ids);
        self::assertContains('ext_pdo_mysql', $ids);
        self::assertContains('vendor', $ids);
        self::assertContains('gd_webp', $ids);
        self::assertContains('uploads_writable', $ids);
    }

    public function testFailsWhenPhpTooOld(): void
    {
        $checker = $this->checker(
            phpVersion: '8.2.99',
            extensions: true,
            imageTypes: self::IMG_JPG | self::IMG_PNG | self::IMG_WEBP,
            writable: true,
            files: true,
            dirs: true,
        );

        self::assertFalse($checker->allRequiredOk());

        $php = $this->checkById($checker, 'php_version');
        self::assertFalse($php['ok']);
        self::assertTrue($php['required']);
        self::assertSame('setup.req.php_version', $php['labelKey']);
    }

    public function testFailsWhenExtensionMissing(): void
    {
        $checker = $this->checker(
            phpVersion: '8.3.12',
            extensions: static fn (string $name): bool => $name !== 'pdo_mysql',
            imageTypes: self::IMG_JPG | self::IMG_PNG | self::IMG_WEBP,
            writable: true,
            files: true,
            dirs: true,
        );

        self::assertFalse($checker->allRequiredOk());
        self::assertFalse($this->checkById($checker, 'ext_pdo_mysql')['ok']);
    }

    public function testFailsWhenVendorMissing(): void
    {
        $checker = $this->checker(
            phpVersion: '8.3.12',
            extensions: true,
            imageTypes: self::IMG_JPG | self::IMG_PNG | self::IMG_WEBP,
            writable: true,
            files: false,
            dirs: true,
        );

        self::assertFalse($checker->allRequiredOk());
        self::assertFalse($this->checkById($checker, 'vendor')['ok']);
    }

    public function testFailsWhenVarNotWritable(): void
    {
        $checker = $this->checker(
            phpVersion: '8.3.12',
            extensions: true,
            imageTypes: self::IMG_JPG | self::IMG_PNG | self::IMG_WEBP,
            writable: static fn (string $path): bool => !str_ends_with($path, '/var'),
            files: true,
            dirs: true,
        );

        self::assertFalse($checker->allRequiredOk());
        self::assertFalse($this->checkById($checker, 'var_writable')['ok']);
    }

    public function testFailsWhenGdWebpMissing(): void
    {
        $checker = $this->checker(
            phpVersion: '8.3.12',
            extensions: true,
            imageTypes: self::IMG_JPG | self::IMG_PNG,
            writable: true,
            files: true,
            dirs: true,
        );

        self::assertFalse($checker->allRequiredOk());
        self::assertFalse($this->checkById($checker, 'gd_webp')['ok']);
        self::assertTrue($this->checkById($checker, 'gd_jpeg')['ok']);
    }

    /**
     * @param callable(string): bool|bool $extensions
     * @param callable(string): bool|bool $writable
     * @param callable(string): bool|bool $files
     * @param callable(string): bool|bool $dirs
     */
    private function checker(
        string $phpVersion,
        callable|bool $extensions,
        int $imageTypes,
        callable|bool $writable,
        callable|bool $files,
        callable|bool $dirs,
    ): SetupRequirements {
        $extFn = is_bool($extensions)
            ? static fn (string $name): bool => $extensions
            : $extensions;
        $writableFn = is_bool($writable)
            ? static fn (string $path): bool => $writable
            : $writable;
        $fileFn = is_bool($files)
            ? static fn (string $path): bool => $files
            : $files;
        $dirFn = is_bool($dirs)
            ? static fn (string $path): bool => $dirs
            : $dirs;

        return new SetupRequirements(
            '/tmp/mt2-cms-req-test',
            $phpVersion,
            $extFn,
            static fn (): int => $imageTypes,
            $writableFn,
            $fileFn,
            $dirFn,
        );
    }

    /**
     * @return array{id: string, labelKey: string, ok: bool, required: bool, detail?: string}
     */
    private function checkById(SetupRequirements $checker, string $id): array
    {
        foreach ($checker->checks() as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        self::fail('Missing check id: ' . $id);
    }
}
