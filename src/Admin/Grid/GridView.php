<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridView
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public readonly GridSpec $spec,
        public readonly GridQuery $query,
        public readonly array $rows,
        public readonly int $total,
        public readonly int $totalPages,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->spec->action,
            'massActionPath' => $this->spec->massActionPath,
            'i18nPrefix' => $this->spec->i18nPrefix,
            'idField' => $this->spec->idField,
            'columns' => $this->spec->columns,
            'filters' => $this->spec->filters,
            'extraFilters' => $this->spec->extraFilters,
            'massActions' => $this->spec->massActions,
            'searchable' => $this->spec->searchable,
            'perPageOptions' => $this->spec->perPageOptions,
            'hasMassActions' => $this->spec->hasMassActions(),
            'hasFilters' => $this->spec->extraFilters !== [] || $this->hasColumnFilters(),
            'hasColumnFilters' => $this->hasColumnFilters(),
            'query' => $this->query->q ?? '',
            'page' => $this->query->page,
            'perPage' => $this->query->perPage,
            'sort' => $this->query->sort,
            'dir' => $this->query->dir,
            'filterValues' => $this->query->filters,
            'rows' => $this->rows,
            'total' => $this->total,
            'totalPages' => $this->totalPages,
            'defaultSort' => $this->spec->defaultSort,
            'defaultPerPage' => $this->spec->defaultPerPage,
            'defaultDir' => $this->spec->defaultDir,
            'urls' => [
                'base' => GridUrl::build($this->spec->action, $this->query, [], $this->spec),
                'reset' => GridUrl::reset($this->spec->action),
            ],
        ];
    }

    private function hasColumnFilters(): bool
    {
        foreach ($this->spec->columns as $column) {
            if (isset($column['columnFilter']) && is_array($column['columnFilter'])) {
                return true;
            }
        }

        return false;
    }

    public static function paginate(int $total, GridQuery $query): int
    {
        return max(1, (int) ceil($total / max(1, $query->perPage)));
    }

    public static function clampPage(GridQuery $query, int $totalPages): GridQuery
    {
        if ($query->page <= $totalPages) {
            return $query;
        }

        return new GridQuery(
            $query->q,
            $totalPages,
            $query->perPage,
            $query->sort,
            $query->dir,
            $query->filters,
        );
    }
}
