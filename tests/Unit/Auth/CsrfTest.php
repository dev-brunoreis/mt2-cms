<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testTokenIsStableAndValidates(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('invalid'));
    }
}
