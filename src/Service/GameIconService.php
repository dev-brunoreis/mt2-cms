<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Icon\PngEncoder;
use Mt2Cms\Game\Icon\TgaDecoder;

class GameIconService
{
    public const KIND_ITEM = 'item';
    public const KIND_FACE = 'face';

    /** @var array<int, string> */
    private const FACE_BY_JOB = [
        0 => 'warrior_m.tga',
        1 => 'assassin_m.tga',
        2 => 'sura_m.tga',
        3 => 'shaman_m.tga',
        4 => 'warrior_w.tga',
        5 => 'assassin_w.tga',
        6 => 'sura_w.tga',
        7 => 'shaman_w.tga',
    ];

    /** @var array<string, bool> */
    private array $exists = [];

    public function __construct(
        private string $iconRoot,
        private string $cacheRoot,
        private TgaDecoder $decoder = new TgaDecoder(),
        private PngEncoder $encoder = new PngEncoder(),
    ) {
    }

    public function itemUrl(mixed $vnum): ?string
    {
        $id = (int) $vnum;

        return $this->has(self::KIND_ITEM, $id) ? '/game/icon/item/' . $id : null;
    }

    public function faceUrl(mixed $job): ?string
    {
        $id = (int) $job;

        return $this->has(self::KIND_FACE, $id) ? '/game/icon/face/' . $id : null;
    }

    public function png(string $kind, int $id): ?string
    {
        $source = $this->sourcePath($kind, $id);

        if ($source === null) {
            return null;
        }

        $cache = $this->cachePath($kind, $id);

        if (is_file($cache) && filemtime($cache) >= filemtime($source)) {
            $cached = file_get_contents($cache);

            return is_string($cached) && $cached !== '' ? $cached : null;
        }

        $raw = file_get_contents($source);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $image = $this->decoder->decode($raw);

        if ($image === null) {
            return null;
        }

        $png = $this->encoder->encode($image['width'], $image['height'], $image['rgba']);
        $this->writeCache($cache, $png);

        return $png;
    }

    public function has(string $kind, int $id): bool
    {
        $key = $kind . ':' . $id;

        if (!isset($this->exists[$key])) {
            $this->exists[$key] = $this->sourcePath($kind, $id) !== null;
        }

        return $this->exists[$key];
    }

    private function sourcePath(string $kind, int $id): ?string
    {
        if ($kind === self::KIND_ITEM) {
            return $this->itemFile($id);
        }

        if ($kind === self::KIND_FACE) {
            return $this->faceFile($id);
        }

        return null;
    }

    private function itemFile(int $vnum): ?string
    {
        if ($vnum < 1) {
            return null;
        }

        $dir = $this->iconRoot . '/item';
        $names = [sprintf('%05d.tga', $vnum)];

        if ($vnum % 10 !== 0) {
            $names[] = sprintf('%05d.tga', $vnum - ($vnum % 10));
        }

        foreach ($names as $name) {
            $resolved = $this->safeFile($dir, $name);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function faceFile(int $job): ?string
    {
        $name = self::FACE_BY_JOB[$job] ?? null;

        if ($name === null) {
            return null;
        }

        return $this->safeFile($this->iconRoot . '/face', $name);
    }

    private function safeFile(string $directory, string $filename): ?string
    {
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            return null;
        }

        $path = $directory . '/' . $filename;

        if (!is_file($path)) {
            return null;
        }

        $realFile = realpath($path);
        $realDir = realpath($directory);

        if ($realFile === false || $realDir === false) {
            return null;
        }

        if (!str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }

    private function cachePath(string $kind, int $id): string
    {
        return $this->cacheRoot . '/' . $kind . '/' . $id . '.png';
    }

    private function writeCache(string $path, string $png): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents($path, $png);
    }
}
