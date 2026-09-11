<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridSpec
{
    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $filters
     * @param list<array<string, mixed>> $massActions
     * @param list<int> $perPageOptions
     * @param list<string> $sortWhitelist
     */
    public function __construct(
        public readonly string $action,
        public readonly string $i18nPrefix,
        public readonly array $columns,
        public readonly ?string $massActionPath = null,
        public readonly array $filters = [],
        public readonly array $massActions = [],
        public readonly bool $searchable = true,
        public readonly string $idField = 'id',
        public readonly string $massIdType = 'int',
        public readonly array $perPageOptions = [20, 50, 100],
        public readonly int $defaultPerPage = 20,
        public readonly string $defaultSort = 'id',
        public readonly string $defaultDir = 'desc',
        public readonly array $sortWhitelist = [],
    ) {
    }

    public function hasMassActions(): bool
    {
        return $this->massActionPath !== null && $this->massActions !== [];
    }

    public function allowsMassAction(string $action): bool
    {
        if ($action === '') {
            return false;
        }

        foreach ($this->massActions as $entry) {
            if (($entry['id'] ?? '') === $action) {
                return true;
            }
        }

        return false;
    }

    public function usesStringMassIds(): bool
    {
        return $this->massIdType === 'string';
    }

    /**
     * @return list<string>
     */
    public function allowedSortColumns(): array
    {
        if ($this->sortWhitelist !== []) {
            return $this->sortWhitelist;
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
}
