<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

/**
 * Sort/page an in-memory row list for nested detail tabs (guild members, etc.).
 */
final class InMemoryGrid
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $sortKeys column key => row field
     * @return list<array<string, mixed>>
     */
    public static function apply(array $rows, GridQuery $query, array $sortKeys): array
    {
        $field = $sortKeys[$query->sort] ?? null;

        if (is_string($field) && $field !== '') {
            usort($rows, static function (array $a, array $b) use ($field, $query): int {
                $left = $a[$field] ?? null;
                $right = $b[$field] ?? null;

                if (is_numeric($left) && is_numeric($right)) {
                    $cmp = $left <=> $right;
                } else {
                    $cmp = strcasecmp((string) $left, (string) $right);
                }

                return $query->dir === 'asc' ? $cmp : -$cmp;
            });
        }

        return array_slice($rows, $query->offset(), $query->perPage);
    }
}
