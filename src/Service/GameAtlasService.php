<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Icon\DdsDecoder;
use Mt2Cms\Game\Icon\PngEncoder;
use Mt2Cms\Game\Icon\TgaDecoder;
use Mt2Cms\Game\Map\MapCatalog;

class GameAtlasService
{
    /** @var array<int, string|null> */
    private array $png = [];

    public function __construct(
        private MapCatalog $maps,
        private string $cacheRoot,
        private DdsDecoder $dds = new DdsDecoder(),
        private TgaDecoder $tga = new TgaDecoder(),
        private PngEncoder $encoder = new PngEncoder(),
    ) {
    }

    public function png(int $mapIndex): ?string
    {
        $map = $this->maps->find($mapIndex);

        if ($map === null) {
            return null;
        }

        $index = (int) $map['index'];

        if (array_key_exists($index, $this->png)) {
            return $this->png[$index];
        }

        $meta = $this->maps->atlasMeta((string) $map['folder']);

        if ($meta === null) {
            $this->png[$index] = null;

            return null;
        }

        $source = $meta['source'];
        $cache = $this->cacheRoot . '/' . $index . '.png';

        if (is_file($cache) && filemtime($cache) >= filemtime($source)) {
            $cached = file_get_contents($cache);

            if (is_string($cached) && $cached !== '') {
                $this->png[$index] = $cached;

                return $cached;
            }
        }

        $raw = file_get_contents($source);

        if (!is_string($raw) || $raw === '') {
            $this->png[$index] = null;

            return null;
        }

        $image = str_ends_with(strtolower($source), '.tga')
            ? $this->tga->decode($raw)
            : $this->dds->decode($raw);

        if ($image === null) {
            $this->png[$index] = null;

            return null;
        }

        $cropped = $this->crop(
            $image['rgba'],
            $image['width'],
            $image['height'],
            (int) $meta['left'],
            (int) $meta['top'],
            (int) $meta['right'],
            (int) $meta['bottom'],
        );

        $png = $this->encoder->encode($cropped['width'], $cropped['height'], $cropped['rgba']);
        $this->writeCache($cache, $png);
        $this->png[$index] = $png;

        return $png;
    }

    /**
     * @return array{width: int, height: int, rgba: string}
     */
    private function crop(string $rgba, int $width, int $height, int $left, int $top, int $right, int $bottom): array
    {
        $cropW = $right > $left ? min($width, $right) - max(0, $left) : $width;
        $cropH = $bottom > $top ? min($height, $bottom) - max(0, $top) : $height;
        $x0 = max(0, $left);
        $y0 = max(0, $top);

        if ($cropW === $width && $cropH === $height && $x0 === 0 && $y0 === 0) {
            return ['width' => $width, 'height' => $height, 'rgba' => $rgba];
        }

        $out = '';
        $stride = $width * 4;

        for ($y = 0; $y < $cropH; $y++) {
            $out .= substr($rgba, (($y0 + $y) * $stride) + ($x0 * 4), $cropW * 4);
        }

        return ['width' => $cropW, 'height' => $cropH, 'rgba' => $out];
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
