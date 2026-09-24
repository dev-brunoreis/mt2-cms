<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Setup;

use Mt2Cms\Setup\SetupDatabaseDefaults;
use PHPUnit\Framework\TestCase;

final class SetupDatabaseDefaultsTest extends TestCase
{
    public function testHostPhpMapsComposeServiceNamesToPublishedPorts(): void
    {
        $values = SetupDatabaseDefaults::formValues([
            'DB_HOST' => 'game',
            'DB_PORT' => '3306',
            'DB_USER' => 'root',
            'CMS_DB_HOST' => 'mysql',
            'CMS_DB_PORT' => '3306',
            'CMS_DB_USER' => 'root',
        ], false);

        self::assertSame('127.0.0.1', $values['db_host']);
        self::assertSame('8001', $values['db_port']);
        self::assertSame('127.0.0.1', $values['cms_db_host']);
        self::assertSame('8002', $values['cms_db_port']);
        self::assertSame('root', $values['cms_db_user']);
    }

    public function testContainerKeepsComposeServiceNames(): void
    {
        $values = SetupDatabaseDefaults::formValues([], true);

        self::assertSame('game', $values['db_host']);
        self::assertSame('3306', $values['db_port']);
        self::assertSame('mysql', $values['cms_db_host']);
        self::assertSame('3306', $values['cms_db_port']);
    }

    public function testHostPhpWithoutEnvUsesPublishedPorts(): void
    {
        $values = SetupDatabaseDefaults::formValues([], false);

        self::assertSame('127.0.0.1', $values['db_host']);
        self::assertSame('8001', $values['db_port']);
        self::assertSame('127.0.0.1', $values['cms_db_host']);
        self::assertSame('8002', $values['cms_db_port']);
        self::assertSame('root', $values['cms_db_user']);
    }

    public function testRemoteHostsAreLeftAlone(): void
    {
        $game = SetupDatabaseDefaults::forConnection('10.0.0.5', '3306', 'game', '8001', false);
        $cms = SetupDatabaseDefaults::forConnection('127.0.0.1', '3306', 'mysql', '8002', false);

        self::assertSame(['host' => '10.0.0.5', 'port' => '3306'], $game);
        self::assertSame(['host' => '127.0.0.1', 'port' => '3306'], $cms);
    }
}
