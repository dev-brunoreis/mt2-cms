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

    /**
     * @param array<string, array{sql: string, op?: string}> $map
     * @return array{0: string, 1: list<mixed>}
     */
    public static function where(GridQuery $query, array $map): array
    {
        $clauses = [];
        $params = [];

        foreach ($query->filters as $key => $value) {
            if ($value === '' || !isset($map[$key])) {
                continue;
            }

            $cfg = $map[$key];
            $sql = $cfg['sql'] ?? '';
            $op = $cfg['op'] ?? 'like';

            if (!is_string($sql) || $sql === '') {
                continue;
            }

            if ($op === 'eq') {
                $clauses[] = $sql . ' = ?';
                $params[] = $value;
                continue;
            }

            if ($op === 'date') {
                $clauses[] = 'DATE(' . $sql . ') = ?';
                $params[] = $value;
                continue;
            }

            if ($op === 'unix_date') {
                $start = strtotime($value . ' 00:00:00');
                $end = strtotime($value . ' 23:59:59');

                if ($start === false || $end === false) {
                    continue;
                }

                $clauses[] = $sql . ' BETWEEN ? AND ?';
                $params[] = $start;
                $params[] = $end;
                continue;
            }

            if ($op === 'eq_or_like' && self::isNumericToken($value)) {
                $clauses[] = $sql . ' = ?';
                $params[] = $value;
                continue;
            }

            $clauses[] = 'CAST(' . $sql . ' AS CHAR) LIKE ?';
            $params[] = '%' . self::escapeLike($value) . '%';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @param array{0: string, 1: list<mixed>} $where
     * @param list<mixed> $params
     * @return array{0: string, 1: list<mixed>}
     */
    public static function append(array $where, string $clause, array $params = []): array
    {
        [$sql, $existing] = $where;

        if ($sql === '') {
            return [' WHERE ' . $clause, $params];
        }

        return [$sql . ' AND ' . $clause, array_merge($existing, $params)];
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $filters
     * @param array<string, string> $ops
     */
    public static function rowMatches(array $row, array $filters, array $ops = []): bool
    {
        foreach ($filters as $key => $value) {
            if ($value === '') {
                continue;
            }

            $cell = (string) ($row[$key] ?? '');
            $op = $ops[$key] ?? 'like';

            if (!self::cellMatches($cell, $value, $op)) {
                return false;
            }
        }

        return true;
    }

    private static function cellMatches(string $cell, string $value, string $op): bool
    {
        if ($op === 'eq') {
            return $cell === $value
                || (self::isNumericToken($cell) && self::isNumericToken($value) && (int) $cell === (int) $value);
        }

        if ($op === 'date' || $op === 'unix_date') {
            if ($op === 'unix_date' && self::isNumericToken($cell)) {
                $formatted = date('Y-m-d', (int) $cell);

                return $formatted === $value;
            }

            return str_starts_with($cell, $value);
        }

        if ($op === 'eq_or_like' && self::isNumericToken($value)) {
            return self::isNumericToken($cell) && (int) $cell === (int) $value;
        }

        return mb_stripos($cell, $value) !== false;
    }

    private static function isNumericToken(string $value): bool
    {
        return $value !== '' && preg_match('/^-?\d+$/', $value) === 1;
    }
}
