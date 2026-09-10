<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

/**
 * File-backed IP + action rate limiter (no Redis or DB required).
 */
class RateLimiter
{
    public function __construct(
        private int $maxAttempts = 10,
        private int $windowSeconds = 900,
        private ?string $storageDir = null,
    ) {
    }

    public function tooManyAttempts(string $bucket): bool
    {
        $this->prune($bucket);

        return count($this->hits($bucket)) >= $this->maxAttempts;
    }

    public function hit(string $bucket): void
    {
        $this->prune($bucket);

        $hits = $this->hits($bucket);
        $hits[] = time();
        $this->write($bucket, $hits);
    }

    public function clear(string $bucket): void
    {
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
        $path = $this->pathFor($bucket);

        if (!is_file($path)) {
            return [];
        }

        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            if ($raw === false || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
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
        $this->ensureStorageDir();
        $path = $this->pathFor($bucket);
        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($hits, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (\JsonException) {
            // Ignore write failures.
        } finally {
            fclose($handle);
        }
    }

    private function prune(string $bucket): void
    {
        $cutoff = time() - $this->windowSeconds;
        $hits = array_values(array_filter(
            $this->hits($bucket),
            static fn (int $at): bool => $at >= $cutoff,
        ));

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

    private function ensureStorageDir(): void
    {
        $dir = $this->dir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }
}
