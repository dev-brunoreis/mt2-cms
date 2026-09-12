<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

use Mt2Cms\Support\Env;

/**
 * Request helpers (client IP behind reverse proxy).
 */
final class Request
{
    public static function clientIp(): string
    {
        if (self::trustsProxy()) {
            $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

            if ($forwarded !== '') {
                $parts = array_map('trim', explode(',', $forwarded));
                $candidate = end($parts);

                if (self::isValidIp($candidate)) {
                    return $candidate;
                }
            }

            $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));

            if ($realIp !== '' && self::isValidIp($realIp)) {
                return $realIp;
            }
        }

        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return $remote !== '' ? $remote : '0.0.0.0';
    }

    private static function trustsProxy(): bool
    {
        $value = Env::getInstance()->get('APP_TRUST_PROXY', '0');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    private static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
