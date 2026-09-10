<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

use Mt2Cms\Model\Env;

/**
 * Derives PHP session cookie name and path from the request URI.
 */
final class SessionConfig
{
    public const PUBLIC_NAME = 'MT2CMS';
    public const ADMIN_NAME = 'MT2ADMIN';

    public static function forRequestUri(string $uri): self
    {
        $path = $uri;

        if (false !== $pos = strpos($uri, '?')) {
            $path = substr($uri, 0, $pos);
        }

        $path = rawurldecode($path);

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return new self(self::ADMIN_NAME, '/admin');
        }

        return new self(self::PUBLIC_NAME, '/');
    }

    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {
    }

    public static function isSecureRequest(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';

        if ($https !== '' && $https !== 'off') {
            return true;
        }

        if (!self::trustsProxy()) {
            return false;
        }

        $proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

        return $proto === 'https';
    }

    private static function trustsProxy(): bool
    {
        $value = Env::getInstance()->get('APP_TRUST_PROXY', '0');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }
}
