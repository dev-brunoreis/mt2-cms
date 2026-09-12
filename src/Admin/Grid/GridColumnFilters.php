<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

use Mt2Cms\Game\Jobs;

final class GridColumnFilters
{
    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $explicitFilters
     * @param array<string, string> $sqlSortMap
     * @return list<array<string, mixed>>
     */
    public static function decorate(array $columns, array $explicitFilters, array $sqlSortMap, bool $auto = true): array
    {
        $explicit = [];

        foreach ($explicitFilters as $filter) {
            $key = (string) ($filter['key'] ?? '');

            if ($key !== '') {
                $explicit[$key] = $filter;
            }
        }

        foreach ($columns as &$column) {
            $columnType = (string) ($column['type'] ?? 'text');

            if ($columnType === 'actions' || ($column['filter'] ?? true) === false) {
                continue;
            }

            $columnKey = (string) ($column['key'] ?? '');
            $sort = (string) ($column['sort'] ?? '');
            $filterKey = (string) ($column['filterKey'] ?? $columnKey);
            $match = $explicit[$filterKey] ?? $explicit[$sort] ?? $explicit[$columnKey] ?? null;

            if (!$auto && $match === null) {
                continue;
            }

            $inferred = self::infer($column, $filterKey);

            if ($match !== null) {
                $inferred['key'] = (string) $match['key'];
                $inferred['type'] = (string) ($match['type'] ?? $inferred['type']);
                $inferred['options'] = is_array($match['options'] ?? null) ? $match['options'] : ($inferred['options'] ?? []);
                $inferred['translateOptions'] = (bool) ($match['translateOptions'] ?? $inferred['translateOptions'] ?? true);
                $inferred['preserveOrder'] = (bool) ($match['preserveOrder'] ?? false);
                $inferred['optionKind'] = (string) ($match['optionKind'] ?? $inferred['optionKind'] ?? 'i18n');
                $inferred['op'] = self::opForFilterType(
                    $inferred['type'],
                    (string) ($column['filterType'] ?? $columnType),
                );
            }

            if ($inferred['type'] === 'select' && ($inferred['options'] ?? []) === []) {
                $inferred['type'] = 'text';
                $inferred['op'] = self::opForFilterType('text', $columnType);
            }

            $sql = $column['filterSql'] ?? null;

            if ($sql === false) {
                unset($inferred['sql']);
            } else {
                $resolved = is_string($sql) && $sql !== ''
                    ? $sql
                    : ($sqlSortMap[$sort] ?? $sqlSortMap[$inferred['key']] ?? $sqlSortMap[$columnKey] ?? null);

                if (is_string($resolved) && $resolved !== '') {
                    $inferred['sql'] = $resolved;
                }
            }

            $column['columnFilter'] = $inferred;
        }

        unset($column);

        return $columns;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $explicitFilters
     * @return list<array<string, mixed>>
     */
    public static function extraFilters(array $columns, array $explicitFilters): array
    {
        $used = [];

        foreach ($columns as $column) {
            $key = $column['columnFilter']['key'] ?? null;

            if (is_string($key) && $key !== '') {
                $used[$key] = true;
            }
        }

        $extra = [];

        foreach ($explicitFilters as $filter) {
            $key = (string) ($filter['key'] ?? '');

            if ($key !== '' && !isset($used[$key])) {
                $extra[] = $filter;
            }
        }

        return $extra;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $extraFilters
     * @return list<array<string, mixed>>
     */
    public static function requestFilters(array $columns, array $extraFilters): array
    {
        $all = $extraFilters;

        foreach ($columns as $column) {
            if (isset($column['columnFilter']) && is_array($column['columnFilter'])) {
                $all[] = $column['columnFilter'];
            }
        }

        return $all;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, array{sql: string, op: string}>
     */
    public static function sqlMap(array $columns): array
    {
        $map = [];

        foreach ($columns as $column) {
            $filter = $column['columnFilter'] ?? null;

            if (!is_array($filter)) {
                continue;
            }

            $key = (string) ($filter['key'] ?? '');
            $sql = $filter['sql'] ?? null;

            if ($key === '' || !is_string($sql) || $sql === '') {
                continue;
            }

            $map[$key] = [
                'sql' => $sql,
                'op' => (string) ($filter['op'] ?? 'like'),
            ];
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, string>
     */
    public static function opsMap(array $columns): array
    {
        $map = [];

        foreach ($columns as $column) {
            $filter = $column['columnFilter'] ?? null;

            if (!is_array($filter)) {
                continue;
            }

            $key = (string) ($filter['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $map[$key] = (string) ($filter['op'] ?? 'like');
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $column
     * @return array<string, mixed>
     */
    private static function infer(array $column, string $filterKey): array
    {
        $columnType = (string) ($column['filterType'] ?? $column['type'] ?? 'text');
        $options = $column['filterOptions'] ?? null;
        $optionKind = (string) ($column['optionKind'] ?? 'i18n');

        $filter = [
            'key' => $filterKey,
            'type' => 'text',
            'op' => 'like',
            'options' => [],
            'translateOptions' => true,
            'optionKind' => $optionKind,
            'preserveOrder' => false,
        ];

        if (is_array($options) && $options !== []) {
            $filter['type'] = 'select';
            $filter['op'] = 'eq';
            $filter['options'] = $options;
            $filter['translateOptions'] = $optionKind === 'i18n';

            return $filter;
        }

        return match ($columnType) {
            'badge' => [
                ...$filter,
                'type' => 'select',
                'op' => 'eq',
                'options' => self::badgeOptions($column),
            ],
            'bool' => [
                ...$filter,
                'type' => 'select',
                'op' => 'eq',
                'options' => [
                    '1' => 'admin.yes',
                    '0' => 'admin.no',
                ],
            ],
            'job' => [
                ...$filter,
                'type' => 'select',
                'op' => 'eq',
                'options' => self::jobOptions(),
                'translateOptions' => false,
                'optionKind' => 'job',
            ],
            'empire' => [
                ...$filter,
                'type' => 'select',
                'op' => 'eq',
                'options' => [
                    '1' => 'empire.1',
                    '2' => 'empire.2',
                    '3' => 'empire.3',
                ],
            ],
            'proto_limit', 'proto_apply', 'proto_token' => [
                ...$filter,
                'type' => 'select',
                'op' => 'eq',
                'translateOptions' => false,
                'optionKind' => 'proto',
            ],
            'date', 'unix_date' => [
                ...$filter,
                'type' => 'date',
                'op' => $columnType === 'unix_date' ? 'unix_date' : 'date',
            ],
            'number', 'money', 'muted', 'playtime', 'range' => [
                ...$filter,
                'type' => 'text',
                'op' => 'eq_or_like',
            ],
            default => $filter,
        };
    }

    /**
     * @param array<string, mixed> $column
     * @return array<string, string>
     */
    private static function badgeOptions(array $column): array
    {
        $options = [];
        $map = $column['badgeMap'] ?? [];

        if (!is_array($map)) {
            return [];
        }

        foreach ($map as $value => $meta) {
            $options[(string) $value] = is_array($meta) ? (string) ($meta['label'] ?? $value) : (string) $meta;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function jobOptions(): array
    {
        $options = [];

        foreach (Jobs::raceIds() as $race) {
            $options[(string) $race] = (string) $race;
        }

        return $options;
    }

    private static function opForFilterType(string $filterType, string $columnType): string
    {
        return match ($filterType) {
            'select' => 'eq',
            'date' => $columnType === 'unix_date' ? 'unix_date' : 'date',
            default => match ($columnType) {
                'number', 'money', 'muted', 'playtime', 'range' => 'eq_or_like',
                default => 'like',
            },
        };
    }
}
