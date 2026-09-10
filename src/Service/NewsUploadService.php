<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

class NewsUploadService
{
    private const MAX_BYTES = 2_097_152;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(private string $publicDir)
    {
    }

    /**
     * @param array<string, mixed> $file
     */
    public function store(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('admin.news.upload_failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('admin.news.upload_failed');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('admin.news.upload_too_large');
        }

        $info = @getimagesize($tmp);

        if ($info === false || !isset($info['mime'])) {
            throw new \InvalidArgumentException('admin.news.upload_invalid');
        }

        $mime = (string) $info['mime'];
        $ext = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($ext === null) {
            throw new \InvalidArgumentException('admin.news.upload_invalid');
        }

        $year = date('Y');
        $month = date('m');
        $relativeDir = 'uploads/news/' . $year . '/' . $month;
        $absoluteDir = rtrim($this->publicDir, '/') . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('admin.news.upload_failed');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new \RuntimeException('admin.news.upload_failed');
        }

        return '/' . $relativeDir . '/' . $filename;
    }
}
