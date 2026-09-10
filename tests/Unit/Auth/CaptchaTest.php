<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\Captcha;
use PHPUnit\Framework\TestCase;

final class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
    }

    public function testVerifyAcceptsMatchingAnswerOnce(): void
    {
        $captcha = new Captcha('public');
        $svg = $captcha->svg();

        self::assertStringContainsString('<svg', $svg);

        preg_match_all('/>([23456789ABCDEFGHJKLMNPQRSTUVWXYZ])</', $svg, $matches);
        $code = implode('', $matches[1] ?? []);
        self::assertSame(5, strlen($code));

        self::assertTrue($captcha->verify($code));
        self::assertFalse($captcha->verify($code));
    }

    public function testVerifyRejectsWrongAnswer(): void
    {
        $captcha = new Captcha('admin');
        $captcha->svg();

        self::assertFalse($captcha->verify('WRONG'));
    }
}
