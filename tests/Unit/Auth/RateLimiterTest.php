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
        $this->removeTree($this->dir);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                chmod($full, 0700);
                $this->removeTree($full);
            } else {
                unlink($full);
            }
        }

        chmod($path, 0700);
        rmdir($path);
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

    public function testFailClosedWhenStorageIsNotWritable(): void
    {
        $readOnlyDir = $this->dir . '/readonly';
        mkdir($readOnlyDir, 0555, true);

        $limiter = new RateLimiter(10, 60, $readOnlyDir);
        $bucket = 'login:127.0.0.1';

        self::assertTrue($limiter->tooManyAttempts($bucket));

        $limiter->hit($bucket);
        self::assertTrue($limiter->tooManyAttempts($bucket));
    }
}
