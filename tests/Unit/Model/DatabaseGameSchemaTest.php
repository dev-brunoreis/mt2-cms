<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Model;

use Mt2Cms\Model\Database;
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
}
