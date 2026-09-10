<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

/**
 * Idle session timeout. Expired sessions are destroyed and restarted as guest.
 */
final class SessionGuard
{
    private const LAST_ACTIVITY = '_last_activity';

    public const ADMIN_IDLE_SECONDS = 1800;
    public const PUBLIC_IDLE_SECONDS = 7200;

    public static function enforceIdleTimeout(SessionConfig $config): void
    {
        $now = time();
        $last = $_SESSION[self::LAST_ACTIVITY] ?? null;

        if (is_int($last) && self::isExpired($last, $now, $config->name)) {
            self::destroyAndRestart();

            return;
        }

        $_SESSION[self::LAST_ACTIVITY] = $now;
    }

    public static function isExpired(int $lastActivity, int $now, string $sessionName): bool
    {
        $limit = $sessionName === SessionConfig::ADMIN_NAME
            ? self::ADMIN_IDLE_SECONDS
            : self::PUBLIC_IDLE_SECONDS;

        return ($now - $lastActivity) > $limit;
    }

    private static function destroyAndRestart(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_start();
        $_SESSION[self::LAST_ACTIVITY] = time();
    }
}
