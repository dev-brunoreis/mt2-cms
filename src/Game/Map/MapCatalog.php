<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Map;

use Mt2Cms\Game\GameProfile;

class MapCatalog
{
    /** @var list<array<string, mixed>>|null */
    private ?array $maps = null;

    public function __construct(
        private GameProfile $profile,
    ) {
    }

    /**
     * @return list<array{
     *   index: int,
     *   folder: string,
     *   name: string,
     *   cell_scale: int,
     *   size_x: int,
     *   size_y: int,
     *   base_x: int,
     *   base_y: int,
     *   width: int,
     *   height: int,
     *   has_atlas: bool
     * }>
     */
    public function all(): array
    {
        if ($this->maps === null) {
            $this->maps = $this->load();
        }

        return $this->maps;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $index): ?array
    {
        $base = $this->baseIndex($index);

        foreach ($this->all() as $map) {
            if ($map['index'] === $base) {
                return $map;
            }
        }

        return null;
    }

    public function baseIndex(int $mapIndex): int
    {
        if ($mapIndex >= 10000) {
            return intdiv($mapIndex, 10000);
        }

        return $mapIndex;
    }

    /**
     * @param array<string, mixed> $map
     * @return array{left: float, top: float}|null
     */
    public function pointOnAtlas(array $map, int $x, int $y): ?array
    {
        $width = (int) $map['width'];
        $height = (int) $map['height'];

        if ($width < 1 || $height < 1) {
            return null;
        }

        $px = ($x - (int) $map['base_x']) / $width;
        $py = ($y - (int) $map['base_y']) / $height;

        if ($px < -0.05 || $py < -0.05 || $px > 1.05 || $py > 1.05) {
            return null;
        }

        return [
            'left' => max(0.0, min(100.0, $px * 100)),
            'top' => max(0.0, min(100.0, $py * 100)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function load(): array
    {
        $mapsDir = $this->profile->optionalPath('maps');

        if ($mapsDir === null || !is_dir($mapsDir)) {
            return [];
        }

        $indexPath = $mapsDir . '/index';

        if (!is_file($indexPath)) {
            return [];
        }

        $raw = file_get_contents($indexPath);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $out = [];

        foreach (preg_split("/\R/", $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (!preg_match('/^(\d+)\s+(\S+)/', $line, $match)) {
                continue;
            }

            $folder = $match[2];

            if (!$this->safeFolder($folder)) {
                continue;
            }

            $settings = $this->parseSettings($mapsDir . '/' . $folder . '/Setting.txt');
            $index = (int) $match[1];
            $cell = $settings['cell_scale'];
            $sizeX = $settings['size_x'];
            $sizeY = $settings['size_y'];
            $sector = 128 * $cell;

            $out[] = [
                'index' => $index,
                'folder' => $folder,
                'name' => $folder,
                'cell_scale' => $cell,
                'size_x' => $sizeX,
                'size_y' => $sizeY,
                'base_x' => $settings['base_x'],
                'base_y' => $settings['base_y'],
                'width' => $sizeX * $sector,
                'height' => $sizeY * $sector,
                'has_atlas' => $this->hasAtlas($folder),
            ];
        }

        return $out;
    }

    /**
     * @return array{cell_scale: int, size_x: int, size_y: int, base_x: int, base_y: int}
     */
    private function parseSettings(string $path): array
    {
        $defaults = [
            'cell_scale' => 200,
            'size_x' => 1,
            'size_y' => 1,
            'base_x' => 0,
            'base_y' => 0,
        ];

        if (!is_file($path)) {
            return $defaults;
        }

        $raw = file_get_contents($path);

        if (!is_string($raw)) {
            return $defaults;
        }

        foreach (preg_split("/\R/", $raw) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if ($parts === [] || $parts[0] === '') {
                continue;
            }

            if ($parts[0] === 'CellScale' && isset($parts[1])) {
                $defaults['cell_scale'] = max(1, (int) $parts[1]);
            }

            if ($parts[0] === 'MapSize' && isset($parts[1], $parts[2])) {
                $defaults['size_x'] = max(1, (int) $parts[1]);
                $defaults['size_y'] = max(1, (int) $parts[2]);
            }

            if ($parts[0] === 'BasePosition' && isset($parts[1], $parts[2])) {
                $defaults['base_x'] = (int) $parts[1];
                $defaults['base_y'] = (int) $parts[2];
            }
        }

        return $defaults;
    }

    public function atlasMeta(string $folder): ?array
    {
        if (!$this->safeFolder($folder)) {
            return null;
        }

        $atlasRoot = $this->profile->optionalPath('atlas');

        if ($atlasRoot === null || !is_dir($atlasRoot)) {
            return null;
        }

        $subPath = $atlasRoot . '/atlas/' . $folder . '/atlas.sub';

        if (!is_file($subPath)) {
            return null;
        }

        $raw = file_get_contents($subPath);

        if (!is_string($raw)) {
            return null;
        }

        $image = null;
        $left = 0;
        $top = 0;
        $right = 0;
        $bottom = 0;

        foreach (preg_split("/\R/", $raw) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^image\s+"?([^"\s]+)"?/i', $line, $match)) {
                $image = basename(str_replace('\\', '/', $match[1]));
            }

            if (preg_match('/^left\s+(\d+)/i', $line, $match)) {
                $left = (int) $match[1];
            }

            if (preg_match('/^top\s+(\d+)/i', $line, $match)) {
                $top = (int) $match[1];
            }

            if (preg_match('/^right\s+(\d+)/i', $line, $match)) {
                $right = (int) $match[1];
            }

            if (preg_match('/^bottom\s+(\d+)/i', $line, $match)) {
                $bottom = (int) $match[1];
            }
        }

        if ($image === null || !$this->safeFile($image)) {
            return null;
        }

        $source = $this->safeResolve($atlasRoot, $image);

        if ($source === null) {
            return null;
        }

        return [
            'source' => $source,
            'left' => $left,
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
        ];
    }

    private function hasAtlas(string $folder): bool
    {
        return $this->atlasMeta($folder) !== null;
    }

    private function safeFolder(string $folder): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $folder);
    }

    private function safeFile(string $filename): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_.-]+\.(dds|tga)$/i', $filename);
    }

    private function safeResolve(string $directory, string $filename): ?string
    {
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
}
