<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin\Grid;

use Mt2Cms\Admin\Grid\GridColumnFilters;
use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use PHPUnit\Framework\TestCase;

final class GridColumnFiltersTest extends TestCase
{
    public function testDecorateInfersSelectForBadgeAndDateForDates(): void
    {
        $columns = GridColumnFilters::decorate(
            [
                ['key' => 'status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'OK' => ['label' => 'ok'],
                    'BLOCK' => ['label' => 'block'],
                ]],
                ['key' => 'created_at', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'login', 'sort' => 'login', 'type' => 'text'],
                ['key' => 'secret', 'type' => 'text', 'filter' => false],
            ],
            [],
            [
                'status' => 'a.status',
                'created_at' => 'a.created_at',
                'login' => 'a.login',
            ],
            true,
        );

        self::assertSame('select', $columns[0]['columnFilter']['type']);
        self::assertSame('eq', $columns[0]['columnFilter']['op']);
        self::assertSame('a.status', $columns[0]['columnFilter']['sql']);
        self::assertSame('date', $columns[1]['columnFilter']['type']);
        self::assertSame('date', $columns[1]['columnFilter']['op']);
        self::assertSame('text', $columns[2]['columnFilter']['type']);
        self::assertSame('like', $columns[2]['columnFilter']['op']);
        self::assertArrayNotHasKey('columnFilter', $columns[3]);
    }

    public function testSearchableFalseOnlyKeepsExplicitColumnFilters(): void
    {
        $columns = GridColumnFilters::decorate(
            [
                ['key' => 'name', 'sort' => 'name', 'type' => 'text'],
                ['key' => 'job', 'sort' => 'job', 'type' => 'job'],
            ],
            [
                ['key' => 'range', 'type' => 'select', 'options' => ['5m' => 'five']],
            ],
            ['name' => 'p.name', 'job' => 'p.job'],
            false,
        );

        self::assertArrayNotHasKey('columnFilter', $columns[0]);
        self::assertArrayNotHasKey('columnFilter', $columns[1]);
        self::assertSame(
            [['key' => 'range', 'type' => 'select', 'options' => ['5m' => 'five']]],
            GridColumnFilters::extraFilters($columns, [
                ['key' => 'range', 'type' => 'select', 'options' => ['5m' => 'five']],
            ]),
        );
    }

    public function testDefinitionSpecMovesRangeToExtraFilters(): void
    {
        $spec = GridDefinition::create('/admin', 'admin.dashboard')
            ->searchable(false)
            ->columns([
                ['key' => 'name', 'sort' => 'name', 'type' => 'text'],
            ])
            ->orderBy(['name' => 'p.name'])
            ->filters([
                ['key' => 'range', 'label' => 'range', 'type' => 'select', 'options' => ['5m' => 'five']],
            ])
            ->spec();

        self::assertCount(1, $spec->extraFilters);
        self::assertSame('range', $spec->extraFilters[0]['key']);
        self::assertSame('range', $spec->filters[0]['key']);
        self::assertArrayNotHasKey('columnFilter', $spec->columns[0]);
    }

    public function testWhereBuildsEqLikeAndDateClauses(): void
    {
        $query = new GridQuery(null, 1, 20, 'id', 'desc', [
            'status' => 'OK',
            'login' => 'adm_in',
            'created_at' => '2026-09-12',
            'unknown' => 'x',
        ]);

        [$sql, $params] = GridSql::where($query, [
            'status' => ['sql' => 'a.status', 'op' => 'eq'],
            'login' => ['sql' => 'a.login', 'op' => 'like'],
            'created_at' => ['sql' => 'a.created_at', 'op' => 'date'],
        ]);

        self::assertSame(
            ' WHERE a.status = ? AND CAST(a.login AS CHAR) LIKE ? AND DATE(a.created_at) = ?',
            $sql,
        );
        self::assertSame(['OK', '%adm\\_in%', '2026-09-12'], $params);

        [$appended, $appendedParams] = GridSql::append([$sql, $params], 'a.id > ?', [1]);
        self::assertSame($sql . ' AND a.id > ?', $appended);
        self::assertSame(['OK', '%adm\\_in%', '2026-09-12', 1], $appendedParams);
    }

    public function testRowMatchesUsesOps(): void
    {
        $row = ['status' => 'OK', 'name' => 'Warrior', 'level' => '30'];

        self::assertTrue(GridSql::rowMatches($row, ['status' => 'OK'], ['status' => 'eq']));
        self::assertFalse(GridSql::rowMatches($row, ['status' => 'OKAY'], ['status' => 'eq']));
        self::assertTrue(GridSql::rowMatches($row, ['name' => 'war'], ['name' => 'like']));
        self::assertTrue(GridSql::rowMatches($row, ['level' => '30'], ['level' => 'eq_or_like']));
        self::assertFalse(GridSql::rowMatches($row, ['level' => '3'], ['level' => 'eq_or_like']));
    }
}
