<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin;

use Mt2Cms\Admin\RoleSlug;
use PHPUnit\Framework\TestCase;

final class RoleSlugTest extends TestCase
{
    public function testFromLabelNormalizesText(): void
    {
        self::assertSame('finance-team', RoleSlug::fromLabel('Finance Team'));
    }

    public function testFromLabelRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleSlug::fromLabel('   ');
    }

    public function testFromLabelRejectsSuper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleSlug::fromLabel('Super');
    }

    public function testAssertValidAcceptsSlug(): void
    {
        self::assertSame('moderator', RoleSlug::assertValid('Moderator'));
    }

    public function testAssertValidRejectsReservedSuper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleSlug::assertValid('super');
    }

    public function testAssertValidRejectsInvalidCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleSlug::assertValid('bad slug!');
    }
}
