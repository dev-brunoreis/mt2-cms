<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin;

use Mt2Cms\Admin\AdminAuditMeta;
use PHPUnit\Framework\TestCase;

final class AdminAuditMetaTest extends TestCase
{
    public function testChangedKeepsOnlyDifferentFields(): void
    {
        $meta = AdminAuditMeta::changed(
            ['login' => 'old', 'cash' => '100', 'status' => 'OK'],
            ['login' => 'new', 'cash' => 100, 'status' => 'OK'],
        );

        self::assertSame(['login' => 'old'], $meta['before']);
        self::assertSame(['login' => 'new'], $meta['after']);
    }

    public function testChangedTreatsBoolAndTinyintAsEqual(): void
    {
        $meta = AdminAuditMeta::changed(
            ['enabled' => 1, 'flag' => 0],
            ['enabled' => true, 'flag' => false],
        );

        self::assertSame([], $meta);
    }

    public function testSanitizeStripsNestedSecrets(): void
    {
        $clean = AdminAuditMeta::sanitize([
            'before' => ['login' => 'a', 'password' => 'secret', 'social_id' => '1234567'],
            'after' => ['login' => 'b', 'password_hash' => 'x'],
            '_csrf' => 'token',
        ]);

        self::assertSame([
            'before' => ['login' => 'a'],
            'after' => ['login' => 'b'],
        ], $clean);
    }

    public function testDisplayColumnsSplitBeforeAndAfter(): void
    {
        $columns = AdminAuditMeta::displayColumns([
            'before' => ['cash' => 10],
            'after' => ['cash' => 20],
            'slug' => 'editor',
        ]);

        self::assertSame([['key' => 'cash', 'value' => '10']], $columns['before']);
        self::assertSame([
            ['key' => 'cash', 'value' => '20'],
            ['key' => 'slug', 'value' => 'editor'],
        ], $columns['after']);
    }

    public function testDisplayColumnsPutsLegacyMetaInAfter(): void
    {
        $columns = AdminAuditMeta::displayColumns(['role' => 'support', 'item_vnum' => 123]);

        self::assertSame([], $columns['before']);
        self::assertSame([
            ['key' => 'role', 'value' => 'support'],
            ['key' => 'item_vnum', 'value' => '123'],
        ], $columns['after']);
    }
}
