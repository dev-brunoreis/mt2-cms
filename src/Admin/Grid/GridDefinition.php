<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

/**
 * Colocated grid config: columns, filters, sort map, and mass actions in one place.
 * Live under Admin/Grid/Definitions; repositories use sortMap() in listForGrid SQL.
 */
final class GridDefinition
{
    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $filters
     * @param list<array<string, mixed>> $massActions
     * @param array<string, string> $sqlSortMap
     * @param list<int> $perPageOptions
     */
    private function __construct(
        private string $action,
        private string $i18nPrefix,
        private array $columns,
        private ?string $massActionPath,
        private array $filters,
        private array $massActions,
        private bool $searchable,
        private string $idField,
        private string $massIdType,
        private array $perPageOptions,
        private int $defaultPerPage,
        private string $defaultSort,
        private string $defaultDir,
        private array $sqlSortMap,
    ) {
    }

    public static function create(string $action, string $i18nPrefix): self
    {
        return new self(
            action: $action,
            i18nPrefix: $i18nPrefix,
            columns: [],
            massActionPath: null,
            filters: [],
            massActions: [],
            searchable: true,
            idField: 'id',
            massIdType: 'int',
            perPageOptions: [20, 50, 100],
            defaultPerPage: 20,
            defaultSort: 'id',
            defaultDir: 'desc',
            sqlSortMap: [],
        );
    }

    /**
     * @param list<array<string, mixed>> $columns
     */
    public function columns(array $columns): self
    {
        return $this->cloneWith(['columns' => $columns]);
    }

    /**
     * @param array<string, string> $map column key => SQL expression
     */
    public function orderBy(array $map): self
    {
        return $this->cloneWith(['sqlSortMap' => $map]);
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    public function filters(array $filters): self
    {
        return $this->cloneWith(['filters' => $filters]);
    }

    /**
     * @param list<array<string, mixed>> $massActions
     */
    public function massActions(string $path, array $massActions): self
    {
        return $this->cloneWith([
            'massActionPath' => $path,
            'massActions' => $massActions,
        ]);
    }

    public function idField(string $idField): self
    {
        return $this->cloneWith(['idField' => $idField]);
    }

    public function massIdType(string $type): self
    {
        return $this->cloneWith(['massIdType' => $type]);
    }

    public function searchable(bool $searchable): self
    {
        return $this->cloneWith(['searchable' => $searchable]);
    }

    public function defaultSort(string $column, string $dir = 'desc'): self
    {
        return $this->cloneWith([
            'defaultSort' => $column,
            'defaultDir' => $dir,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return $this->cloneWith($overrides);
    }

    /**
     * @param array<string, string> $options
     */
    public function filterOptions(string $key, array $options, bool $translateOptions = true): self
    {
        $filters = $this->filters;

        foreach ($filters as $index => $filter) {
            if (($filter['key'] ?? '') !== $key) {
                continue;
            }

            $filters[$index]['options'] = $options;
            $filters[$index]['translateOptions'] = $translateOptions;

            break;
        }

        return $this->cloneWith(['filters' => $filters]);
    }

    public function spec(): GridSpec
    {
        $sortWhitelist = $this->sortWhitelist();

        return new GridSpec(
            action: $this->action,
            i18nPrefix: $this->i18nPrefix,
            columns: $this->columns,
            massActionPath: $this->massActionPath,
            filters: $this->filters,
            massActions: $this->massActions,
            searchable: $this->searchable,
            idField: $this->idField,
            massIdType: $this->massIdType,
            perPageOptions: $this->perPageOptions,
            defaultPerPage: $this->defaultPerPage,
            defaultSort: $this->defaultSort,
            defaultDir: $this->defaultDir,
            sortWhitelist: $sortWhitelist,
        );
    }

    /**
     * @return array<string, string>
     */
    public function sortMap(): array
    {
        if ($this->sqlSortMap !== []) {
            return $this->sqlSortMap;
        }

        $map = [];

        foreach ($this->columns as $column) {
            $sort = $column['sort'] ?? null;
            $key = $column['key'] ?? null;

            if (is_string($sort) && $sort !== '' && is_string($key) && $key !== '') {
                $map[$sort] = $sort;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function sortWhitelist(): array
    {
        if ($this->sqlSortMap !== []) {
            return array_keys($this->sqlSortMap);
        }

        $keys = [];

        foreach ($this->columns as $column) {
            $sort = $column['sort'] ?? null;

            if (is_string($sort) && $sort !== '') {
                $keys[] = $sort;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function cloneWith(array $changes): self
    {
        $clone = clone $this;

        foreach ($changes as $key => $value) {
            $clone->{$key} = $value;
        }

        return $clone;
    }
}
