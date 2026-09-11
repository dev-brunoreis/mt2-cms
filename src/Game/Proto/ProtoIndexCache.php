<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

/**
 * File-backed index cache for proto list/filter (avoids re-parsing tab files every request).
 */
final class ProtoIndexCache
{
    public function __construct(
        private string $cacheDir,
    ) {
    }

    /**
     * @return list<array<string, string>>
     */
    public function all(TabProtoTable $table, string $cacheKey): array
    {
        $cacheFile = $this->cachePath($cacheKey);
        $sources = [$table->protoMtime(), $table->namesMtime()];
        $latest = max($sources);

        if (is_file($cacheFile) && filemtime($cacheFile) >= $latest) {
            $json = file_get_contents($cacheFile);

            if ($json !== false) {
                $decoded = json_decode($json, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $rows = $table->all();
        $this->write($cacheFile, $rows);

        return $rows;
    }

    public function invalidate(string $cacheKey): void
    {
        $path = $this->cachePath($cacheKey);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function write(string $path, array $rows): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0750, true) && !is_dir($this->cacheDir)) {
            return;
        }

        try {
            $json = json_encode($rows, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException) {
            // Game proto files may contain legacy non-UTF-8 bytes; skip cache, keep serving from source.
            return;
        }

        file_put_contents($path, $json, LOCK_EX);
        @chmod($path, 0640);
    }

    private function cachePath(string $cacheKey): string
    {
        $safe = preg_replace('/[^a-z0-9_-]+/i', '-', $cacheKey) ?? 'proto';

        return rtrim($this->cacheDir, '/') . '/proto-index-' . $safe . '.json';
    }
}
