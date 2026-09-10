<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mt2-rate-limit-' . uniqid('', true);
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testHitAndTooManyAttempts(): void
    {
        $limiter = new RateLimiter(2, 60, $this->dir);
        $bucket = 'login:127.0.0.1';

        self::assertFalse($limiter->tooManyAttempts($bucket));
        $limiter->hit($bucket);
        self::assertFalse($limiter->tooManyAttempts($bucket));
        $limiter->hit($bucket);
        self::assertTrue($limiter->tooManyAttempts($bucket));
    }

    public function testClearResetsBucket(): void
    {
        $limiter = new RateLimiter(1, 60, $this->dir);
        $bucket = 'setup';

        $limiter->hit($bucket);
        self::assertTrue($limiter->tooManyAttempts($bucket));
        $limiter->clear($bucket);
        self::assertFalse($limiter->tooManyAttempts($bucket));
    }
}
