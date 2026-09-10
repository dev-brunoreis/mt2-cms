<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

class DownloadUploadService
{
    private const MAX_BYTES = 524_288_000;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['zip', 'rar', '7z', 'exe', 'tar', 'gz', 'bz2', 'patch', 'bin'];

    public function __construct(private string $storageDir)
    {
    }

    /**
     * @param array<string, mixed> $file
     * @return array{stored_name: string, original_name: string}
     */
    public function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('admin.downloads.upload_failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('admin.downloads.upload_failed');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('admin.downloads.upload_too_large');
        }

        $original = $this->safeOriginalName((string) ($file['name'] ?? 'download.bin'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('admin.downloads.upload_invalid');
        }

        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0750, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('admin.downloads.upload_failed');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolutePath = rtrim($this->storageDir, '/') . '/' . $storedName;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new \RuntimeException('admin.downloads.upload_failed');
        }

        @chmod($absolutePath, 0640);

        return ['stored_name' => $storedName, 'original_name' => $original];
    }

    public function absolutePath(string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $storedName)) {
            throw new \InvalidArgumentException('admin.downloads.file_not_found');
        }

        return rtrim($this->storageDir, '/') . '/' . $storedName;
    }

    public function delete(string $storedName): void
    {
        try {
            $path = $this->absolutePath($storedName);
        } catch (\InvalidArgumentException) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function safeOriginalName(string $name): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? 'download';
        $base = trim($base, '._-');

        return mb_substr($base !== '' ? $base : 'download.bin', 0, 180);
    }
}
