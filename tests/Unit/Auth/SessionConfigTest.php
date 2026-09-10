<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\SessionConfig;
use PHPUnit\Framework\TestCase;

final class SessionConfigTest extends TestCase
{
    public function testPublicRoutesUsePublicCookie(): void
    {
        $config = SessionConfig::forRequestUri('/login');

        self::assertSame(SessionConfig::PUBLIC_NAME, $config->name);
        self::assertSame('/', $config->path);
    }

    public function testSetupUsesPublicCookie(): void
    {
        $config = SessionConfig::forRequestUri('/setup?step=admin');

        self::assertSame(SessionConfig::PUBLIC_NAME, $config->name);
        self::assertSame('/', $config->path);
    }

    public function testAdminRoutesUseAdminCookie(): void
    {
        $config = SessionConfig::forRequestUri('/admin/login');

        self::assertSame(SessionConfig::ADMIN_NAME, $config->name);
        self::assertSame('/admin', $config->path);
    }

    public function testAdminSubpathUsesAdminCookie(): void
    {
        $config = SessionConfig::forRequestUri('/admin/game/accounts');

        self::assertSame(SessionConfig::ADMIN_NAME, $config->name);
        self::assertSame('/admin', $config->path);
    }
}
