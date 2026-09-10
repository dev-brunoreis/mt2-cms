<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin\Grid;

use Mt2Cms\Admin\Grid\GridRequest;
use Mt2Cms\Admin\Grid\GridSpec;
use PHPUnit\Framework\TestCase;

final class GridRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testFromGetWhitelistsSortAndLimit(): void
    {
        $_GET = [
            'sort' => 'evil',
            'dir' => 'asc',
            'limit' => '999',
            'page' => '2',
        ];

        $spec = new GridSpec(
            action: '/admin/test',
            i18nPrefix: 'admin.test',
            columns: [
                ['key' => 'id', 'sort' => 'id'],
                ['key' => 'login', 'sort' => 'login'],
            ],
            defaultSort: 'id',
            defaultDir: 'desc',
            defaultPerPage: 20,
            perPageOptions: [20, 50],
        );

        $query = GridRequest::fromGet($spec);

        self::assertSame('id', $query->sort);
        self::assertSame('asc', $query->dir);
        self::assertSame(20, $query->perPage);
        self::assertSame(2, $query->page);
    }

    public function testMassIdsDedupesAndCaps(): void
    {
        $_POST = [
            'ids' => ['1', '1', '2', '0', 'abc', '3'],
            'mass_action' => 'delete',
        ];

        self::assertSame([1, 2, 3], GridRequest::massIds());
        self::assertSame('delete', GridRequest::massAction());
    }

    public function testMassStringKeysDedupesAndRejectsInvalid(): void
    {
        $_POST = [
            'ids' => ['support', 'support', 'Super', 'bad slug', ''],
        ];

        self::assertSame(['support', 'super'], GridRequest::massStringKeys());
    }

    public function testMassIdsForSpecUsesStringKeysWhenConfigured(): void
    {
        $_POST = ['ids' => ['content', 'bad slug', '1']];

        $intSpec = new GridSpec(
            action: '/admin/system/admins',
            i18nPrefix: 'admin.admins',
            columns: [],
        );
        self::assertSame([1], GridRequest::massIdsForSpec($intSpec));

        $slugSpec = new GridSpec(
            action: '/admin/system/roles',
            i18nPrefix: 'admin.roles',
            columns: [],
            idField: 'slug',
            massIdType: 'string',
        );
        self::assertSame(['content', '1'], GridRequest::massIdsForSpec($slugSpec));
    }
}
