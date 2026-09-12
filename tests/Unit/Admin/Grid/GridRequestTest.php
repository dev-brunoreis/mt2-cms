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

    public function testFromGetValidatesSelectAndDateFilters(): void
    {
        $_GET = [
            'filter' => [
                'status' => 'BLOCK',
                'evil' => 'nope',
                'created_at' => '12-09-2026',
                'role' => '2',
            ],
        ];

        $spec = new GridSpec(
            action: '/admin/test',
            i18nPrefix: 'admin.test',
            columns: [],
            filters: [
                ['key' => 'status', 'type' => 'select', 'options' => ['OK' => 'ok', 'BLOCK' => 'block']],
                ['key' => 'created_at', 'type' => 'date'],
                ['key' => 'role', 'type' => 'select', 'options' => [2 => 'Support']],
                ['key' => 'open', 'type' => 'select', 'options' => []],
            ],
        );

        $query = GridRequest::fromGet($spec);

        self::assertSame(['status' => 'BLOCK', 'role' => '2'], $query->filters);
    }

    public function testFromGetKeepsSelectValueWhenOptionsAreEmpty(): void
    {
        $_GET = ['filter' => ['category_id' => '9']];

        $spec = new GridSpec(
            action: '/admin/test',
            i18nPrefix: 'admin.test',
            columns: [],
            filters: [
                ['key' => 'category_id', 'type' => 'select', 'options' => []],
            ],
        );

        $query = GridRequest::fromGet($spec);

        self::assertSame(['category_id' => '9'], $query->filters);
    }

    public function testFromGetAcceptsIsoDates(): void
    {
        $_GET = ['filter' => ['created_at' => '2026-09-12']];

        $spec = new GridSpec(
            action: '/admin/test',
            i18nPrefix: 'admin.test',
            columns: [],
            filters: [
                ['key' => 'created_at', 'type' => 'date'],
            ],
        );

        $query = GridRequest::fromGet($spec);

        self::assertSame(['created_at' => '2026-09-12'], $query->filters);
    }
}
