<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridUrl
{
    /**
     * @param array<string, scalar|null> $overrides
     */
    public static function build(
        string $action,
        GridQuery $query,
        array $overrides = [],
        ?GridSpec $spec = null,
    ): string {
        $defaultPerPage = $spec?->defaultPerPage ?? 20;
        $defaultSort = $spec?->defaultSort ?? 'id';
        $defaultDir = $spec?->defaultDir ?? 'desc';

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

        if ($limit !== $defaultPerPage) {
            $params['limit'] = $limit;
        }

        if (array_key_exists('sort', $overrides) || $sort !== $defaultSort) {
            $params['sort'] = $sort;
        }

        if (array_key_exists('dir', $overrides) || $dir !== $defaultDir) {
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

    public static function sort(
        string $action,
        GridQuery $query,
        string $column,
        ?GridSpec $spec = null,
    ): string {
        $dir = 'asc';

        if ($query->sort === $column && $query->dir === 'asc') {
            $dir = 'desc';
        }

        return self::build($action, $query, [
            'sort' => $column,
            'dir' => $dir,
            'page' => 1,
        ], $spec);
    }

    public static function page(string $action, GridQuery $query, int $page, ?GridSpec $spec = null): string
    {
        return self::build($action, $query, ['page' => $page], $spec);
    }

    public static function limit(string $action, GridQuery $query, int $limit, ?GridSpec $spec = null): string
    {
        return self::build($action, $query, [
            'limit' => $limit,
            'page' => 1,
        ], $spec);
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
        $spec = self::specFromGrid($grid);

        return self::build((string) ($grid['action'] ?? '/admin'), $query, $overrides, $spec);
    }

    /**
     * @param array<string, mixed> $grid
     */
    public static function sortFromGrid(array $grid, string $column): string
    {
        $query = self::queryFromGrid($grid);
        $spec = self::specFromGrid($grid);

        return self::sort((string) ($grid['action'] ?? '/admin'), $query, $column, $spec);
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

    /**
     * @param array<string, mixed> $grid
     */
    private static function specFromGrid(array $grid): ?GridSpec
    {
        if (!isset($grid['defaultSort'], $grid['defaultPerPage'], $grid['defaultDir'])) {
            return null;
        }

        return new GridSpec(
            action: (string) ($grid['action'] ?? '/admin'),
            i18nPrefix: (string) ($grid['i18nPrefix'] ?? 'admin.grid'),
            columns: [],
            defaultPerPage: (int) $grid['defaultPerPage'],
            defaultSort: (string) $grid['defaultSort'],
            defaultDir: (string) $grid['defaultDir'],
        );
    }
}
