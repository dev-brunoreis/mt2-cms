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

    public function testTcpHostRewritesLocalhostWhenPortIsNotMysqlDefault(): void
    {
        self::assertSame('127.0.0.1', Database::tcpHost('localhost', '8001'));
        self::assertSame('localhost', Database::tcpHost('localhost', '3306'));
        self::assertSame('127.0.0.1', Database::tcpHost('127.0.0.1', '8001'));
    }
}
