<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\SessionConfig;
use Mt2Cms\Auth\SessionGuard;
use PHPUnit\Framework\TestCase;

final class SessionGuardTest extends TestCase
{
    public function testAdminSessionExpiresAfterThirtyMinutes(): void
    {
        $now = 1_700_000_000;
        $last = $now - SessionGuard::ADMIN_IDLE_SECONDS - 1;

        self::assertTrue(SessionGuard::isExpired($last, $now, SessionConfig::ADMIN_NAME));
    }

    public function testAdminSessionStillValidWithinWindow(): void
    {
        $now = 1_700_000_000;
        $last = $now - SessionGuard::ADMIN_IDLE_SECONDS;

        self::assertFalse(SessionGuard::isExpired($last, $now, SessionConfig::ADMIN_NAME));
    }

    public function testPublicSessionExpiresAfterTwoHours(): void
    {
        $now = 1_700_000_000;
        $last = $now - SessionGuard::PUBLIC_IDLE_SECONDS - 1;

        self::assertTrue(SessionGuard::isExpired($last, $now, SessionConfig::PUBLIC_NAME));
    }

    public function testPublicSessionStillValidWithinWindow(): void
    {
        $now = 1_700_000_000;
        $last = $now - SessionGuard::PUBLIC_IDLE_SECONDS;

        self::assertFalse(SessionGuard::isExpired($last, $now, SessionConfig::PUBLIC_NAME));
    }
}
