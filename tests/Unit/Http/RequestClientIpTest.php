<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Http;

use Mt2Cms\Http\Request;
use Mt2Cms\Support\Env;
use PHPUnit\Framework\TestCase;

final class RequestClientIpTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Env::$instance = null;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        putenv('APP_TRUST_PROXY');
        unset($_ENV['APP_TRUST_PROXY']);
        Env::$instance = null;
    }

    public function testUsesRemoteAddrWhenProxyNotTrusted(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.10';

        self::assertSame('203.0.113.10', Request::clientIp());
    }

    public function testUsesLastForwardedHopWhenProxyTrusted(): void
    {
        putenv('APP_TRUST_PROXY=1');
        $_ENV['APP_TRUST_PROXY'] = '1';
        Env::$instance = null;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.10';

        self::assertSame('203.0.113.10', Request::clientIp());
    }

    public function testFallsBackToRemoteAddrWhenForwardedInvalid(): void
    {
        putenv('APP_TRUST_PROXY=1');
        $_ENV['APP_TRUST_PROXY'] = '1';
        Env::$instance = null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

        self::assertSame('203.0.113.10', Request::clientIp());
    }
}
