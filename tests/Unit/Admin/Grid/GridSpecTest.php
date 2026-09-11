<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin\Grid;

use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Admin\Grid\GridSql;
use PHPUnit\Framework\TestCase;

final class GridSpecTest extends TestCase
{
    public function testAllowsMassActionUsesSpecWhitelist(): void
    {
        $spec = new GridSpec(
            action: '/admin/game/accounts',
            i18nPrefix: 'admin.accounts',
            columns: [],
            massActionPath: '/admin/game/accounts/mass',
            massActions: [
                ['id' => 'block', 'label' => 'admin.grid.block'],
                ['id' => 'delete', 'label' => 'admin.grid.delete'],
            ],
        );

        self::assertTrue($spec->hasMassActions());
        self::assertTrue($spec->allowsMassAction('block'));
        self::assertTrue($spec->allowsMassAction('delete'));
        self::assertFalse($spec->allowsMassAction('enable'));
        self::assertFalse($spec->allowsMassAction(''));
    }

    public function testOrderByIgnoresUnknownSortKeys(): void
    {
        $query = new GridQuery(null, 1, 20, 'injected; DROP TABLE', 'asc', []);

        self::assertSame(
            ' ORDER BY id DESC',
            GridSql::orderBy($query, ['name' => 'p.name'], 'id DESC'),
        );

        $allowed = new GridQuery(null, 1, 20, 'name', 'asc', []);

        self::assertSame(
            ' ORDER BY p.name ASC',
            GridSql::orderBy($allowed, ['name' => 'p.name'], 'id DESC'),
        );
    }
}
