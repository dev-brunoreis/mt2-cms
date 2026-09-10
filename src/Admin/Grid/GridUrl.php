<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridUrl
{
    /**
     * @param array<string, scalar|null> $overrides
     */
    public static function build(string $action, GridQuery $query, array $overrides = []): string
    {
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $params['q'] = $query->q;
        }

        $page = (int) ($overrides['page'] ?? $query->page);
        $limit = (int) ($overrides['limit'] ?? $query->perPage);
        $sort = (string) ($overrides['sort'] ?? $query->sort);
        $dir = (string) ($overrides['dir'] ?? $query->dir);

        if ($page > 1) {
            $params['page'] = $page;
        }

        if ($limit !== 20) {
            $params['limit'] = $limit;
        }

        if (array_key_exists('sort', $overrides) || $sort !== 'id') {
            $params['sort'] = $sort;
        }

        if (array_key_exists('dir', $overrides) || $dir !== 'desc') {
            $params['dir'] = $dir;
        }

        $filters = $query->filters;

        if (array_key_exists('filters', $overrides) && is_array($overrides['filters'])) {
            $filters = $overrides['filters'];
        }

        foreach ($filters as $key => $value) {
            if ($value !== '') {
                $params['filter'][$key] = $value;
            }
        }

        if (array_key_exists('clear_filters', $overrides) && $overrides['clear_filters']) {
            unset($params['filter']);
        }

        if ($params === []) {
            return $action;
        }

        return $action . '?' . http_build_query($params);
    }

    public static function sort(string $action, GridQuery $query, string $column): string
    {
        $dir = 'asc';

        if ($query->sort === $column && $query->dir === 'asc') {
            $dir = 'desc';
        }

        return self::build($action, $query, [
            'sort' => $column,
            'dir' => $dir,
            'page' => 1,
        ]);
    }

    public static function page(string $action, GridQuery $query, int $page): string
    {
        return self::build($action, $query, ['page' => $page]);
    }

    public static function limit(string $action, GridQuery $query, int $limit): string
    {
        return self::build($action, $query, [
            'limit' => $limit,
            'page' => 1,
        ]);
    }

    public static function reset(string $action): string
    {
        return $action;
    }

    /**
     * @param array<string, mixed> $grid
     * @param array<string, scalar|null> $overrides
     */
    public static function fromGrid(array $grid, array $overrides = []): string
    {
        $query = self::queryFromGrid($grid);

        return self::build((string) ($grid['action'] ?? '/admin'), $query, $overrides);
    }

    /**
     * @param array<string, mixed> $grid
     */
    public static function sortFromGrid(array $grid, string $column): string
    {
        $query = self::queryFromGrid($grid);

        return self::sort((string) ($grid['action'] ?? '/admin'), $query, $column);
    }

    /**
     * @param array<string, mixed> $grid
     */
    private static function queryFromGrid(array $grid): GridQuery
    {
        $q = trim((string) ($grid['query'] ?? ''));

        return new GridQuery(
            $q !== '' ? $q : null,
            max(1, (int) ($grid['page'] ?? 1)),
            max(1, (int) ($grid['perPage'] ?? 20)),
            (string) ($grid['sort'] ?? 'id'),
            (string) ($grid['dir'] ?? 'desc'),
            is_array($grid['filterValues'] ?? null) ? $grid['filterValues'] : [],
        );
    }
}
