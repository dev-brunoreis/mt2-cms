<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

class EnvWriter
{
    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'DB_HOST',
        'DB_PORT',
        'DB_USER',
        'DB_PASSWORD',
        'THEME',
        'LOCALE',
        'APP_INSTALLED',
        'APP_KEY',
        'APP_TRUST_PROXY',
        'CMS_DB_HOST',
        'CMS_DB_PORT',
        'CMS_DB_USER',
        'CMS_DB_PASSWORD',
        'CMS_DB_NAME',
    ];

    /**
     * @param array<string, string> $values
     */
    public function write(array $values, string $path): void
    {
        $this->persist($this->filterValues($values), $path);
    }

    /**
     * Merge keys into an existing .env without removing other entries.
     *
     * @param array<string, string> $values
     */
    public function upsert(array $values, string $path): void
    {
        $merged = $this->parseExisting($path);

        foreach ($this->filterValues($values) as $key => $value) {
            $merged[$key] = $value;
        }

        $this->persist($merged, $path);
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function filterValues(array $values): array
    {
        $filtered = [];

        foreach ($values as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new \InvalidArgumentException('setup.invalid_env_value');
            }

            $filtered[$key] = $value;
        }

        if ($filtered === []) {
            throw new \InvalidArgumentException('setup.empty_env');
        }

        return $filtered;
    }

    /**
     * @return array<string, string>
     */
    private function parseExisting(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $parsed = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '' || !in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            $parsed[$key] = $this->unescapeValue(trim($value));
        }

        return $parsed;
    }

    /**
     * @param array<string, string> $values
     */
    private function persist(array $values, string $path): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('setup.env_not_writable');
        }

        $lines = [];

        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->escapeValue($value);
        }

        $content = implode("\n", $lines) . "\n";
        $tempPath = $path . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($tempPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('setup.env_write_failed');
        }

        if (!rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new \RuntimeException('setup.env_write_failed');
        }

        $this->applyPermissions($path);
    }

    private function unescapeValue(string $value): string
    {
        if ($value === '""' || $value === "''") {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $inner = substr($value, 1, -1);

            return str_replace(['\\"', '\\\\'], ['"', '\\'], $inner);
        }

        return $value;
    }

    private function applyPermissions(string $path): void
    {
        @chmod($path, 0640);
    }

    private function escapeValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\']/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
