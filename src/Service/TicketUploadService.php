<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

class TicketUploadService
{
    public const MAX_BYTES = 2_097_152;
    public const MAX_FILES = 5;

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(private string $storageDir)
    {
    }

    /**
     * Normalize PHP multi-upload into a flat list. Skips empty slots.
     *
     * @param array<string, mixed>|null $filesField
     * @return list<array<string, mixed>>
     */
    public function normalizeUploads(?array $filesField): array
    {
        if ($filesField === null || !isset($filesField['error'])) {
            return [];
        }

        if (!is_array($filesField['error'])) {
            if ((int) $filesField['error'] === UPLOAD_ERR_NO_FILE) {
                return [];
            }

            return [$filesField];
        }

        $out = [];
        $count = count($filesField['error']);

        for ($i = 0; $i < $count; $i++) {
            if ((int) $filesField['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $out[] = [
                'name' => $filesField['name'][$i] ?? '',
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Validate uploads without writing. Throws on first invalid file.
     *
     * @param list<array<string, mixed>> $files
     */
    public function assertValidBatch(array $files): void
    {
        if (count($files) > self::MAX_FILES) {
            throw new \InvalidArgumentException('tickets.attachments_too_many');
        }

        foreach ($files as $file) {
            $this->inspect($file);
        }
    }

    /**
     * @param array<string, mixed> $file
     * @return array{stored_name: string, original_name: string, mime: string, size_bytes: int, absolute_path: string}
     */
    public function store(array $file): array
    {
        $inspected = $this->inspect($file);
        $this->ensureStorageDir();

        $storedName = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
        $absolutePath = $this->absolutePath($storedName);

        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new \RuntimeException('tickets.attachment_failed');
        }

        @chmod($absolutePath, 0640);

        return [
            'stored_name' => $storedName,
            'original_name' => $inspected['original_name'],
            'mime' => $inspected['mime'],
            'size_bytes' => $inspected['size_bytes'],
            'absolute_path' => $absolutePath,
        ];
    }

    public function absolutePath(string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $storedName)) {
            throw new \InvalidArgumentException('tickets.attachment_not_found');
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

    /**
     * @param array<string, mixed> $file
     * @return array{ext: string, mime: string, size_bytes: int, original_name: string}
     */
    private function inspect(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('tickets.attachment_failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('tickets.attachment_failed');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('tickets.attachment_too_large');
        }

        $info = @getimagesize($tmp);

        if ($info === false || !isset($info['mime'])) {
            throw new \InvalidArgumentException('tickets.attachment_invalid');
        }

        $mime = (string) $info['mime'];
        $ext = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($ext === null) {
            throw new \InvalidArgumentException('tickets.attachment_invalid');
        }

        // Reject polyglot / mismatched client MIME when finfo is available.
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmp);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== $mime) {
                    throw new \InvalidArgumentException('tickets.attachment_invalid');
                }
            }
        }

        return [
            'ext' => $ext,
            'mime' => $mime,
            'size_bytes' => $size,
            'original_name' => $this->safeOriginalName((string) ($file['name'] ?? 'evidence.' . $ext), $ext),
        ];
    }

    private function safeOriginalName(string $name, string $ext): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? 'evidence';
        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'evidence';
        }

        if (!str_ends_with(strtolower($base), '.' . $ext)) {
            $base .= '.' . $ext;
        }

        return mb_substr($base, 0, 180);
    }

    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0750, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('tickets.attachment_failed');
        }
    }
}
