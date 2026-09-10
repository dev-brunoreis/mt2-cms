<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Repository;

use Mt2Cms\Model\Database;
use Mt2Cms\Repository\AccountRepository;
use PHPUnit\Framework\TestCase;

final class AccountRepositoryChangePasswordTest extends TestCase
{
    public function testRejectsShortNewPassword(): void
    {
        $repo = new AccountRepository(new Database(['requirePassword' => false]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('error.invalid_password');

        $repo->changePassword(1, 'current', 'ab');
    }

    public function testRejectsLongNewPassword(): void
    {
        $repo = new AccountRepository(new Database(['requirePassword' => false]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('error.invalid_password');

        $repo->changePassword(1, 'current', str_repeat('a', 17));
    }

    public function testRejectsEmptyCurrentPassword(): void
    {
        $repo = new AccountRepository(new Database(['requirePassword' => false]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('account.wrong_password');

        $repo->changePassword(1, '', 'valid1');
    }
}
