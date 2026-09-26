<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseGameSchemaTest extends TestCase
{
    public function testReturnsFalseWhenGameMysqlIsUnreachable(): void
    {
        $this->assertFalse(Database::testGameSchema([
            'host' => '127.0.0.1',
            'port' => '1',
            'user' => 'mt2cms',
            'password' => 'invalid',
        ]));
    }

    public function testEnsureCmsSchemaReturnsFalseWhenMysqlIsUnreachable(): void
    {
        self::assertFalse(Database::ensureCmsSchema([
            'host' => '127.0.0.1',
            'port' => '1',
            'user' => 'cms',
            'password' => 'invalid',
        ]));
    }

    public function testIsCmsCompatibleServerVersion(): void
    {
        self::assertTrue(Database::isCmsCompatibleServerVersion('8.0.36'));
        self::assertTrue(Database::isCmsCompatibleServerVersion('8.4.0-log'));
        self::assertTrue(Database::isCmsCompatibleServerVersion('10.3.22-MariaDB'));
        self::assertTrue(Database::isCmsCompatibleServerVersion('5.5.5-10.11.6-MariaDB'));
        self::assertFalse(Database::isCmsCompatibleServerVersion('5.6.51'));
        self::assertFalse(Database::isCmsCompatibleServerVersion('5.7.44'));
        self::assertFalse(Database::isCmsCompatibleServerVersion('10.2.44-MariaDB'));
    }

    public function testCreateSchemaIfMissingRejectsInvalidIdentifier(): void
    {
        $db = new Database([
            'host' => '127.0.0.1',
            'port' => '1',
            'user' => 'cms',
            'password' => 'invalid',
            'requirePassword' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid identifier');
        $db->createSchemaIfMissing('cms; DROP DATABASE mysql');
    }

    public function testTcpHostRewritesLocalhostWhenPortIsNotMysqlDefault(): void
    {
        self::assertSame('127.0.0.1', Database::tcpHost('localhost', '8001'));
        self::assertSame('localhost', Database::tcpHost('localhost', '3306'));
        self::assertSame('127.0.0.1', Database::tcpHost('127.0.0.1', '8001'));
    }
}
