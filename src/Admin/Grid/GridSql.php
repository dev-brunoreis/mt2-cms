<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridSql
{
    /**
     * @param array<string, string> $map column key => SQL expression
     */
    public static function orderBy(GridQuery $query, array $map, string $fallback = 'id DESC'): string
    {
        $expr = $map[$query->sort] ?? null;

        if (!is_string($expr) || $expr === '') {
            return ' ORDER BY ' . $fallback;
        }

        $dir = $query->dir === 'asc' ? 'ASC' : 'DESC';

        return ' ORDER BY ' . $expr . ' ' . $dir;
    }
}
