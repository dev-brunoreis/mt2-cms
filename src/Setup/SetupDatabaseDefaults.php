<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

final class SetupDatabaseDefaults
{
    public static function inContainer(): bool
    {
        return is_file('/.dockerenv');
    }

    /**
     * @param array<string, string|null> $env
     * @return array{db_host: string, db_port: string, db_user: string, cms_db_host: string, cms_db_port: string, cms_db_user: string}
     */
    public static function formValues(array $env, bool $inContainer): array
    {
        $game = self::forConnection(
            self::string($env, 'DB_HOST') ?? '',
            self::string($env, 'DB_PORT') ?? '',
            'game',
            '8001',
            $inContainer,
        );
        $cms = self::forConnection(
            self::string($env, 'CMS_DB_HOST') ?? '',
            self::string($env, 'CMS_DB_PORT') ?? '',
            'mysql',
            '8002',
            $inContainer,
        );

        return [
            'db_host' => $game['host'],
            'db_port' => $game['port'],
            'db_user' => self::string($env, 'DB_USER') ?? 'root',
            'cms_db_host' => $cms['host'],
            'cms_db_port' => $cms['port'],
            'cms_db_user' => self::string($env, 'CMS_DB_USER') ?? 'root',
        ];
    }

    /**
     * Map Compose service names to published host ports when PHP is not in Docker.
     *
     * @return array{host: string, port: string}
     */
    public static function forConnection(
        string $host,
        string $port,
        string $composeHost,
        string $publishedPort,
        bool $inContainer,
    ): array {
        $host = trim($host);
        $port = trim($port);

        if ($host === '') {
            $host = $inContainer ? $composeHost : '127.0.0.1';
        }

        if ($port === '') {
            $port = $inContainer ? '3306' : $publishedPort;
        }

        if (!$inContainer && strcasecmp($host, $composeHost) === 0) {
            $host = '127.0.0.1';

            if ($port === '3306') {
                $port = $publishedPort;
            }
        }

        return [
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * @param array<string, string|null> $env
     */
    private static function string(array $env, string $key): ?string
    {
        $value = $env[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
