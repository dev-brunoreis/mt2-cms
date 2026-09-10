<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridRunner
{
    /**
     * @param callable(GridQuery): int $countFn
     * @param callable(GridQuery): list<array<string, mixed>> $listFn
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function fetch(
        GridSpec $spec,
        GridQuery $query,
        callable $countFn,
        callable $listFn,
        array $overrides = [],
    ): array {
        $total = $countFn($query);
        $totalPages = GridView::paginate($total, $query);
        $query = GridView::clampPage($query, $totalPages);
        $rows = $listFn($query);

        $view = (new GridView($spec, $query, $rows, $total, $totalPages))->toArray();

        return array_merge($view, $overrides);
    }
}
