<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

class BannerUploadService
{
    private const MAX_BYTES = 5_242_880;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private string $publicDir,
        private ImageVariantService $variants,
    ) {
    }

    /**
     * @param array<string, mixed> $file
     * @return array{original_path: string, variants: array<string, mixed>}
     */
    public function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('admin.banners.upload_failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('admin.banners.upload_failed');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('admin.banners.upload_too_large');
        }

        return $this->storeFromPath($tmp, true);
    }

    /**
     * Import a local image (seed / admin tooling). Does not require is_uploaded_file.
     *
     * @return array{original_path: string, variants: array<string, mixed>}
     */
    public function storeFromPath(string $absoluteSource, bool $isUpload = false): array
    {
        if ($absoluteSource === '' || !is_file($absoluteSource)) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        $info = @getimagesize($absoluteSource);

        if ($info === false || !isset($info['mime'])) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        $mime = (string) $info['mime'];
        $ext = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($ext === null) {
            throw new \InvalidArgumentException('admin.banners.upload_invalid');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = finfo_file($finfo, $absoluteSource);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== $mime) {
                    throw new \InvalidArgumentException('admin.banners.upload_invalid');
                }
            }
        }

        $year = date('Y');
        $month = date('m');
        $hash = bin2hex(random_bytes(16));
        $relativeDir = 'uploads/banners/' . $year . '/' . $month . '/' . $hash;
        $absoluteDir = rtrim($this->publicDir, '/') . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('admin.banners.upload_failed');
        }

        $originalName = 'original.' . $ext;
        $absoluteOriginal = $absoluteDir . '/' . $originalName;

        if ($isUpload) {
            if (!move_uploaded_file($absoluteSource, $absoluteOriginal)) {
                throw new \RuntimeException('admin.banners.upload_failed');
            }
        } elseif (!copy($absoluteSource, $absoluteOriginal)) {
            throw new \RuntimeException('admin.banners.upload_failed');
        }

        @chmod($absoluteOriginal, 0644);

        $variants = $this->variants->generate($absoluteOriginal, $absoluteDir, $relativeDir);

        return [
            'original_path' => '/' . $relativeDir . '/' . $originalName,
            'variants' => $variants,
        ];
    }

    public function deleteByOriginalPath(string $originalPath): void
    {
        $relative = ltrim($originalPath, '/');

        if ($relative === '' || !str_starts_with($relative, 'uploads/banners/')) {
            return;
        }

        $absoluteOriginal = rtrim($this->publicDir, '/') . '/' . $relative;
        $dir = dirname($absoluteOriginal);

        if (!is_dir($dir) || !str_contains($dir, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'banners' . DIRECTORY_SEPARATOR)) {
            return;
        }

        $publicRoot = realpath(rtrim($this->publicDir, '/'));
        $realDir = realpath($dir);

        if ($publicRoot === false || $realDir === false || !str_starts_with($realDir, $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'banners')) {
            return;
        }

        foreach (glob($realDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($realDir);
    }
}
