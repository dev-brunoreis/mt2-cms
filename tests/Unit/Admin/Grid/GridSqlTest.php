<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin\Grid;

use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\InMemoryGrid;
use PHPUnit\Framework\TestCase;

final class GridSqlTest extends TestCase
{
    public function testOrderByUsesWhitelist(): void
    {
        $query = new GridQuery(null, 1, 20, 'name', 'asc', []);
        $sql = GridSql::orderBy($query, ['name' => 'p.name', 'id' => 'p.id'], 'id DESC');

        self::assertSame(' ORDER BY p.name ASC', $sql);
    }

    public function testOrderByFallsBackForUnknownSort(): void
    {
        $query = new GridQuery(null, 1, 20, 'hack; DROP TABLE', 'desc', []);
        $sql = GridSql::orderBy($query, ['name' => 'p.name'], 'id DESC');

        self::assertSame(' ORDER BY id DESC', $sql);
    }

    public function testInMemoryGridSortsAndPages(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'C'],
            ['id' => 2, 'name' => 'A'],
            ['id' => 3, 'name' => 'B'],
        ];
        $query = new GridQuery(null, 1, 2, 'name', 'asc', []);
        $page = InMemoryGrid::apply($rows, $query, ['name' => 'name']);

        self::assertCount(2, $page);
        self::assertSame('A', $page[0]['name']);
        self::assertSame('B', $page[1]['name']);
    }
}
