<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin\Grid;

use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Admin\Grid\GridUrl;
use PHPUnit\Framework\TestCase;

final class GridUrlTest extends TestCase
{
    public function testBuildKeepsExistingTabQuery(): void
    {
        $spec = new GridSpec(
            action: '/admin/logs?tab=goldlog',
            i18nPrefix: 'admin.logs',
            columns: [],
            defaultSort: 'date',
            defaultDir: 'desc',
            defaultPerPage: 20,
        );
        $query = new GridQuery(null, 2, 20, 'date', 'desc', []);

        $url = GridUrl::build('/admin/logs?tab=goldlog', $query, [], $spec);

        self::assertSame('/admin/logs?tab=goldlog&page=2', $url);
    }

    public function testSplitActionAndFormHiddenParams(): void
    {
        [$path, $params] = GridUrl::splitAction('/admin/logs?tab=hack_log');

        self::assertSame('/admin/logs', $path);
        self::assertSame('hack_log', $params['tab'] ?? null);
    }

    public function testBuildWithoutExistingQuery(): void
    {
        $spec = new GridSpec(
            action: '/admin/game/accounts',
            i18nPrefix: 'admin.accounts',
            columns: [],
            defaultSort: 'id',
            defaultPerPage: 20,
        );
        $query = new GridQuery(null, 1, 20, 'id', 'desc', []);

        self::assertSame('/admin/game/accounts', GridUrl::build('/admin/game/accounts', $query, [], $spec));
    }
}
