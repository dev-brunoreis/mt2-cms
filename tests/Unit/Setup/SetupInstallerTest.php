<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Setup;

use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\SetupInstaller;
use PHPUnit\Framework\TestCase;

final class SetupInstallerTest extends TestCase
{
    protected function tearDown(): void
    {
        SetupInstaller::clearCompleteSession();
        $_SESSION = [];
    }

    public function testNextStepSkipsAdminWhenAdminsExist(): void
    {
        $installer = new SetupInstaller(new EnvWriter());

        self::assertSame('done', $installer->nextStepAfterPrepare(1));
        self::assertSame('done', $installer->nextStepAfterPrepare(3));
    }

    public function testNextStepRequiresAdminWhenNoneExist(): void
    {
        $installer = new SetupInstaller(new EnvWriter());

        self::assertSame('admin', $installer->nextStepAfterPrepare(0));
    }

    public function testCompleteSessionRoundTrip(): void
    {
        self::assertFalse(SetupInstaller::hasCompleteSession());
        self::assertNull(SetupInstaller::peekCompleteSession());

        $installer = new SetupInstaller(new EnvWriter());
        $installer->markCompleteInSession(['existing_admins' => true]);

        self::assertTrue(SetupInstaller::hasCompleteSession());
        self::assertSame(
            ['existing_admins' => true],
            SetupInstaller::peekCompleteSession(),
        );

        SetupInstaller::clearCompleteSession();

        self::assertFalse(SetupInstaller::hasCompleteSession());
    }

    public function testCompleteSessionDefaultsExistingAdminsFalse(): void
    {
        $installer = new SetupInstaller(new EnvWriter());
        $installer->markCompleteInSession();

        self::assertSame(
            ['existing_admins' => false],
            SetupInstaller::peekCompleteSession(),
        );
    }
}
