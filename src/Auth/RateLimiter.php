<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

/**
 * File-backed IP + action rate limiter (no Redis or DB required).
 * Fails closed when storage is unavailable.
 */
class RateLimiter
{
    private bool $storageUnavailable = false;

    public function __construct(
        private int $maxAttempts = 10,
        private int $windowSeconds = 900,
        private ?string $storageDir = null,
    ) {
    }

    public function tooManyAttempts(string $bucket): bool
    {
        if ($this->storageUnavailable) {
            return true;
        }

        $this->prune($bucket);

        if ($this->storageUnavailable) {
            return true;
        }

        return count($this->hits($bucket)) >= $this->maxAttempts;
    }

    public function hit(string $bucket): void
    {
        if ($this->storageUnavailable) {
            return;
        }

        $this->prune($bucket);

        if ($this->storageUnavailable) {
            return;
        }

        $hits = $this->hits($bucket);
        $hits[] = time();
        $this->write($bucket, $hits);
    }

    public function clear(string $bucket): void
    {
        if ($this->storageUnavailable) {
            return;
        }

        $path = $this->pathFor($bucket);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return list<int>
     */
    private function hits(string $bucket): array
    {
        if ($this->storageUnavailable) {
            return [];
        }

        if (!$this->ensureStorageDir()) {
            return [];
        }

        $path = $this->pathFor($bucket);

        if (!is_file($path)) {
            return [];
        }

        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            $this->markStorageUnavailable();

            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                $this->markStorageUnavailable();

                return [];
            }

            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            if ($raw === false || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                $this->markStorageUnavailable();

                return [];
            }

            return array_values(array_map('intval', $decoded));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<int> $hits
     */
    private function write(string $bucket, array $hits): void
    {
        if ($this->storageUnavailable) {
            return;
        }

        if (!$this->ensureStorageDir()) {
            return;
        }

        $path = $this->pathFor($bucket);
        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            $this->markStorageUnavailable();

            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                $this->markStorageUnavailable();

                return;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($hits, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (\JsonException) {
            $this->markStorageUnavailable();
        } finally {
            fclose($handle);
        }
    }

    private function prune(string $bucket): void
    {
        if ($this->storageUnavailable) {
            return;
        }

        $cutoff = time() - $this->windowSeconds;
        $hits = array_values(array_filter(
            $this->hits($bucket),
            static fn (int $at): bool => $at >= $cutoff,
        ));

        if ($this->storageUnavailable) {
            return;
        }

        if ($hits === []) {
            $this->clear($bucket);

            return;
        }

        $this->write($bucket, $hits);
    }

    private function pathFor(string $bucket): string
    {
        return $this->dir() . '/' . hash('sha256', $bucket) . '.json';
    }

    private function dir(): string
    {
        if ($this->storageDir !== null) {
            return rtrim($this->storageDir, '/');
        }

        return BASE_DIR . '/var/rate-limit';
    }

    private function ensureStorageDir(): bool
    {
        if ($this->storageUnavailable) {
            return false;
        }

        $dir = $this->dir();

        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->markStorageUnavailable();

            return false;
        }

        if (!is_writable($dir)) {
            $this->markStorageUnavailable();

            return false;
        }

        return true;
    }

    private function markStorageUnavailable(): void
    {
        $this->storageUnavailable = true;
    }
}
