<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Repository;

use Mt2Cms\Model\Database;
use Mt2Cms\Repository\AdminRoleRepository;
use PHPUnit\Framework\TestCase;

final class AdminRoleRepositorySlugExistsTest extends TestCase
{
    public function testSlugExistsIsFalseWhenFetchColumnReturnsNull(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('useDatabase')->willReturnSelf();
        $db->method('fetchColumn')->willReturn(null);

        $repo = new AdminRoleRepository($db);

        self::assertFalse($repo->slugExists('new-role'));
    }

    public function testSlugExistsIsTrueWhenRowFound(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('useDatabase')->willReturnSelf();
        $db->method('fetchColumn')->willReturn('1');

        $repo = new AdminRoleRepository($db);

        self::assertTrue($repo->slugExists('support'));
    }
}
