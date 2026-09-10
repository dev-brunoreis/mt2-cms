<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

final class GridQuery
{
    /**
     * @param array<string, string> $filters
     */
    public function __construct(
        public readonly ?string $q,
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $sort,
        public readonly string $dir,
        public readonly array $filters,
    ) {
    }

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }

    public function filter(string $key, string $default = ''): string
    {
        return $this->filters[$key] ?? $default;
    }
}
