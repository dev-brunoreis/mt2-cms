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
        $dir = dirname($path);

        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('setup.env_not_writable');
        }

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

        $lines = [];

        foreach ($filtered as $key => $value) {
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

    private function applyPermissions(string $path): void
    {
        // Keep the file readable by php-fpm (www-data) on Docker volume mounts.
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
