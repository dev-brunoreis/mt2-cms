<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

/**
 * Resize uploaded images into WebP + JPEG variants for responsive banners.
 */
class ImageVariantService
{
    /** @var list<int> */
    private const WIDTHS = [960, 1440, 1920];

    /**
     * @return array{
     *   960: array{webp: string, jpg: string},
     *   1440: array{webp: string, jpg: string},
     *   1920: array{webp: string, jpg: string},
     *   fallback: string
     * }
     */
    public function generate(string $sourceAbsolutePath, string $targetAbsoluteDir, string $publicUrlPrefix): array
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('admin.banners.gd_required');
        }

        if (!is_file($sourceAbsolutePath)) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        if (!is_dir($targetAbsoluteDir) && !mkdir($targetAbsoluteDir, 0755, true) && !is_dir($targetAbsoluteDir)) {
            throw new \RuntimeException('admin.banners.upload_failed');
        }

        $info = @getimagesize($sourceAbsolutePath);

        if ($info === false || !isset($info[0], $info[1], $info['mime'])) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        $mime = (string) $info['mime'];
        $source = $this->createImage($sourceAbsolutePath, $mime);

        if ($source === false) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        $prefix = '/' . trim($publicUrlPrefix, '/');
        $variants = [];

        try {
            foreach (self::WIDTHS as $width) {
                $targetW = min($width, $srcW);
                $targetH = max(1, (int) round($srcH * ($targetW / max(1, $srcW))));
                $canvas = imagecreatetruecolor($targetW, $targetH);

                if ($canvas === false) {
                    throw new \RuntimeException('admin.banners.upload_failed');
                }

                imagealphablending($canvas, true);
                imagesavealpha($canvas, true);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

                $webpName = $width . '.webp';
                $jpgName = $width . '.jpg';
                $webpPath = $targetAbsoluteDir . '/' . $webpName;
                $jpgPath = $targetAbsoluteDir . '/' . $jpgName;

                if (!imagewebp($canvas, $webpPath, 82)) {
                    imagedestroy($canvas);
                    throw new \RuntimeException('admin.banners.upload_failed');
                }

                if (!imagejpeg($canvas, $jpgPath, 85)) {
                    imagedestroy($canvas);
                    throw new \RuntimeException('admin.banners.upload_failed');
                }

                imagedestroy($canvas);
                @chmod($webpPath, 0644);
                @chmod($jpgPath, 0644);

                $variants[(string) $width] = [
                    'webp' => $prefix . '/' . $webpName,
                    'jpg' => $prefix . '/' . $jpgName,
                ];
            }

            $fallbackName = 'banner.jpg';
            $fallbackPath = $targetAbsoluteDir . '/' . $fallbackName;
            $fallbackW = min(1920, $srcW);
            $fallbackH = max(1, (int) round($srcH * ($fallbackW / max(1, $srcW))));
            $fallback = imagecreatetruecolor($fallbackW, $fallbackH);

            if ($fallback === false) {
                throw new \RuntimeException('admin.banners.upload_failed');
            }

            imagecopyresampled($fallback, $source, 0, 0, 0, 0, $fallbackW, $fallbackH, $srcW, $srcH);

            if (!imagejpeg($fallback, $fallbackPath, 85)) {
                imagedestroy($fallback);
                throw new \RuntimeException('admin.banners.upload_failed');
            }

            imagedestroy($fallback);
            @chmod($fallbackPath, 0644);

            return [
                '960' => $variants['960'],
                '1440' => $variants['1440'],
                '1920' => $variants['1920'],
                'fallback' => $prefix . '/' . $fallbackName,
            ];
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * @return \GdImage|false
     */
    private function createImage(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }
}
