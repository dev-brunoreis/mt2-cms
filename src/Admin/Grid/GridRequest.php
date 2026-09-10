<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridRequest
{
    public static function fromGet(GridSpec $spec): GridQuery
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $search = $spec->searchable && $q !== '' ? $q : null;

        $perPageOptions = $spec->perPageOptions;
        $limit = (int) ($_GET['limit'] ?? $spec->defaultPerPage);

        if (!in_array($limit, $perPageOptions, true)) {
            $limit = $spec->defaultPerPage;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));

        $allowedSort = $spec->allowedSortColumns();
        $sort = (string) ($_GET['sort'] ?? $spec->defaultSort);

        if (!in_array($sort, $allowedSort, true)) {
            $sort = $spec->defaultSort;

            if (!in_array($sort, $allowedSort, true) && $allowedSort !== []) {
                $sort = $allowedSort[0];
            }
        }

        $dir = strtolower((string) ($_GET['dir'] ?? $spec->defaultDir));
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $filters = [];
        $rawFilters = $_GET['filter'] ?? [];

        if (is_array($rawFilters)) {
            foreach ($spec->filters as $filter) {
                $key = (string) ($filter['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $value = trim((string) ($rawFilters[$key] ?? ''));

                if ($value === '') {
                    continue;
                }

                $type = (string) ($filter['type'] ?? 'text');

                if ($type === 'select') {
                    $options = $filter['options'] ?? [];

                    if (is_array($options) && !array_key_exists($value, $options)) {
                        continue;
                    }
                }

                $filters[$key] = $value;
            }
        }

        return new GridQuery($search, $page, $limit, $sort, $dir, $filters);
    }

    /**
     * @return list<int>
     */
    public static function massIds(int $max = 100): array
    {
        $raw = $_POST['ids'] ?? [];
        $ids = [];

        if (!is_array($raw)) {
            return [];
        }

        foreach ($raw as $id) {
            if (!is_numeric($id)) {
                continue;
            }

            $intId = (int) $id;

            if ($intId < 1) {
                continue;
            }

            $ids[$intId] = $intId;

            if (count($ids) >= $max) {
                break;
            }
        }

        return array_values($ids);
    }

    public static function massAction(): string
    {
        return trim((string) ($_POST['mass_action'] ?? ''));
    }
}
