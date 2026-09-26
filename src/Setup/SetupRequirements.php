<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

/**
 * Host dependency checks for CLI (`bin/check-requirements.php`) and the setup wizard.
 * No Composer dependencies — the CLI can require this file when vendor/ is missing.
 */
final class SetupRequirements
{
    /** @var callable(string): bool */
    private $extensionLoaded;

    /** @var callable(): int */
    private $imageTypes;

    /** @var callable(string): bool */
    private $isWritable;

    /** @var callable(string): bool */
    private $isFile;

    /** @var callable(string): bool */
    private $isDir;

    /**
     * @param callable(string): bool|null $extensionLoaded
     * @param callable(): int|null $imageTypes
     * @param callable(string): bool|null $isWritable
     * @param callable(string): bool|null $isFile
     * @param callable(string): bool|null $isDir
     */
    public function __construct(
        private string $baseDir,
        private ?string $phpVersion = null,
        ?callable $extensionLoaded = null,
        ?callable $imageTypes = null,
        ?callable $isWritable = null,
        ?callable $isFile = null,
        ?callable $isDir = null,
    ) {
        $this->baseDir = rtrim($baseDir, '/\\');
        $this->extensionLoaded = $extensionLoaded ?? static fn (string $name): bool => extension_loaded($name);
        $this->imageTypes = $imageTypes ?? static fn (): int => function_exists('imagetypes') ? imagetypes() : 0;
        $this->isWritable = $isWritable ?? static fn (string $path): bool => is_writable($path);
        $this->isFile = $isFile ?? static fn (string $path): bool => is_file($path);
        $this->isDir = $isDir ?? static fn (string $path): bool => is_dir($path);
    }

    /**
     * @return list<array{id: string, labelKey: string, ok: bool, required: bool, detail?: string}>
     */
    public function checks(): array
    {
        $version = $this->phpVersion ?? PHP_VERSION;
        $phpOk = version_compare($version, '8.3.0', '>=') && version_compare($version, '9.0.0', '<');

        $checks = [
            $this->row('php_version', 'setup.req.php_version', $phpOk, true, 'PHP ' . $version),
            $this->extension('pdo_mysql'),
            $this->extension('gd'),
            $this->extension('curl'),
            $this->extension('mbstring'),
            $this->extension('iconv'),
            $this->extension('fileinfo'),
            $this->extension('openssl'),
        ];

        $types = ($this->imageTypes)();
        $gdLoaded = ($this->extensionLoaded)('gd');
        $jpg = defined('IMG_JPG') ? IMG_JPG : 2;
        $png = defined('IMG_PNG') ? IMG_PNG : 4;
        $webp = defined('IMG_WEBP') ? IMG_WEBP : 32;

        $checks[] = $this->row(
            'gd_jpeg',
            'setup.req.gd_jpeg',
            $gdLoaded && (($types & $jpg) === $jpg),
            true,
        );
        $checks[] = $this->row(
            'gd_png',
            'setup.req.gd_png',
            $gdLoaded && (($types & $png) === $png),
            true,
        );
        $checks[] = $this->row(
            'gd_webp',
            'setup.req.gd_webp',
            $gdLoaded && (($types & $webp) === $webp),
            true,
        );

        $vendor = $this->baseDir . '/vendor/autoload.php';
        $checks[] = $this->row(
            'vendor',
            'setup.req.vendor',
            ($this->isFile)($vendor),
            true,
            'vendor/autoload.php',
        );

        $checks[] = $this->writable('base_writable', 'setup.req.base_writable', $this->baseDir, '.env');
        $checks[] = $this->writable('var_writable', 'setup.req.var_writable', $this->baseDir . '/var', 'var/');
        $checks[] = $this->writable(
            'uploads_writable',
            'setup.req.uploads_writable',
            $this->baseDir . '/public/uploads',
            'public/uploads/',
        );

        return $checks;
    }

    public function allRequiredOk(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id: string, labelKey: string, ok: bool, required: bool, detail?: string}
     */
    private function extension(string $name): array
    {
        return $this->row(
            'ext_' . $name,
            'setup.req.ext_' . $name,
            ($this->extensionLoaded)($name),
            true,
            'ext-' . $name,
        );
    }

    /**
     * @return array{id: string, labelKey: string, ok: bool, required: bool, detail?: string}
     */
    private function writable(string $id, string $labelKey, string $path, string $detail): array
    {
        $ok = ($this->isDir)($path) && ($this->isWritable)($path);

        return $this->row($id, $labelKey, $ok, true, $detail);
    }

    /**
     * @return array{id: string, labelKey: string, ok: bool, required: bool, detail?: string}
     */
    private function row(string $id, string $labelKey, bool $ok, bool $required, ?string $detail = null): array
    {
        $row = [
            'id' => $id,
            'labelKey' => $labelKey,
            'ok' => $ok,
            'required' => $required,
        ];

        if ($detail !== null && $detail !== '') {
            $row['detail'] = $detail;
        }

        return $row;
    }
}
