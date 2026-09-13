<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

class SeoImageUploadService
{
    private const MAX_BYTES = 2_097_152;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private string $publicDir)
    {
    }

    public static function isStoredPath(string $path): bool
    {
        return (bool) preg_match('#^/uploads/seo/[a-f0-9]{32}\.(jpg|png|webp)$#', $path);
    }

    /**
     * @param array<string, mixed> $file
     */
    public function store(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('admin.seo.og_image_upload_failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('admin.seo.og_image_upload_failed');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('admin.seo.og_image_upload_too_large');
        }

        $info = @getimagesize($tmp);

        if ($info === false || !isset($info['mime'])) {
            throw new \InvalidArgumentException('admin.seo.og_image_upload_invalid');
        }

        $mime = (string) $info['mime'];
        $ext = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($ext === null) {
            throw new \InvalidArgumentException('admin.seo.og_image_upload_invalid');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmp);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== $mime) {
                    throw new \InvalidArgumentException('admin.seo.og_image_upload_invalid');
                }
            }
        }

        $relativeDir = 'uploads/seo';
        $absoluteDir = rtrim($this->publicDir, '/') . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('admin.seo.og_image_upload_failed');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new \RuntimeException('admin.seo.og_image_upload_failed');
        }

        @chmod($absolutePath, 0644);

        return '/' . $relativeDir . '/' . $filename;
    }

    public function delete(string $publicPath): void
    {
        if (!self::isStoredPath($publicPath)) {
            return;
        }

        $relative = ltrim($publicPath, '/');
        $absolute = rtrim($this->publicDir, '/') . '/' . $relative;
        $publicRoot = realpath(rtrim($this->publicDir, '/'));
        $realFile = realpath($absolute);

        if ($publicRoot === false || $realFile === false) {
            return;
        }

        $seoRoot = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'seo' . DIRECTORY_SEPARATOR;

        if (!str_starts_with($realFile, $seoRoot) || !is_file($realFile)) {
            return;
        }

        @unlink($realFile);
    }
}
