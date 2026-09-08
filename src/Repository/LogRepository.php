<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\Model\Database;

class LogRepository extends Repository
{
    protected function database(): string
    {
        return 'log';
    }

    public function recentLogins(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT type, time, channel, account_id, pid, level, job, playtime
                 FROM `loginlog` ORDER BY time DESC LIMIT ?',
                [$limit],
            ),
        );
    }

    public function tableExists(string $table): bool
    {
        return $this->schemaTableExists($table);
    }

    /**
     * Recent rows from log tables that identify this character.
     *
     * @return list<array{
     *   id: string,
     *   label: string,
     *   columns: list<string>,
     *   dateColumn: string|null,
     *   rows: list<array<string, mixed>>
     * }>
     */
    public function listForCharacter(int $playerId, string $playerName, int $limit = 15): array
    {
        if ($playerId < 1) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $groups = [];

        foreach (LogCatalog::forCharacter() as $log) {
            if (!$this->tableExists($log['table'])) {
                continue;
            }

            [$where, $params] = $this->characterWhere($log['playerColumns'], $playerId, $playerName);

            if ($where === '') {
                continue;
            }

            $params[] = $limit;
            $rows = $this->sanitizeRows(
                $this->db()->fetchAll(
                    'SELECT ' . $this->selectList($log['columns']) . '
                     FROM `' . Database::quoteIdentifier($log['table']) . '`' . $where . '
                     ORDER BY ' . $this->orderBy($log) . '
                     LIMIT ?',
                    $params,
                ),
            );

            if ($rows === []) {
                continue;
            }

            $groups[] = [
                'id' => $log['id'],
                'label' => $log['label'],
                'columns' => $log['columns'],
                'dateColumn' => $log['dateColumn'],
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    /**
     * @param array{q?: string, from?: string, to?: string} $filters
     */
    public function countForAdmin(string $id, array $filters): int
    {
        $log = $this->requireLog($id);

        if (!$this->tableExists($log['table'])) {
            return 0;
        }

        [$where, $params] = $this->adminWhere($log, $filters);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . Database::quoteIdentifier($log['table']) . '`' . $where,
            $params,
        );
    }

    /**
     * @param array{q?: string, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(string $id, int $page, int $perPage, array $filters): array
    {
        $log = $this->requireLog($id);

        if (!$this->tableExists($log['table'])) {
            return [];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->adminWhere($log, $filters);
        $params[] = $perPage;
        $params[] = $offset;

        $order = $this->orderBy($log);

        return $this->sanitizeRows(
            $this->db()->fetchAll(
                'SELECT ' . $this->selectList($log['columns']) . '
                 FROM `' . Database::quoteIdentifier($log['table']) . '`' . $where . '
                 ORDER BY ' . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    /**
     * @return list<array{ip: string, connections: int, first_seen: mixed, last_seen: mixed}>
     */
    public function ipsForAccount(int $accountId): array
    {
        if ($accountId < 1 || !$this->tableExists('loginlog2')) {
            return [];
        }

        return $this->sanitizeRows(
            $this->db()->fetchAll(
                'SELECT ip, COUNT(*) AS connections, MIN(login_time) AS first_seen, MAX(login_time) AS last_seen
                 FROM `loginlog2`
                 WHERE account_id = ?
                   AND ip IS NOT NULL
                   AND TRIM(ip) <> \'\'
                 GROUP BY ip
                 ORDER BY last_seen DESC
                 LIMIT 100',
                [$accountId],
            ),
        );
    }

    /**
     * @param array{q?: string, from?: string, to?: string} $filters
     */
    public function countConnectionIps(array $filters): int
    {
        if (!$this->tableExists('loginlog2')) {
            return 0;
        }

        [$where, $params] = $this->connectionWhere($filters);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM (
                SELECT l.ip, l.account_id
                FROM `loginlog2` l
                LEFT JOIN `account`.`account` a ON a.id = l.account_id
                ' . $where . '
                GROUP BY l.ip, l.account_id
             ) AS ip_rows',
            $params,
        );
    }

    /**
     * @param array{q?: string, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listConnectionIps(int $page, int $perPage, array $filters): array
    {
        if (!$this->tableExists('loginlog2')) {
            return [];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->connectionWhere($filters);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->sanitizeRows(
            $this->db()->fetchAll(
                'SELECT l.ip,
                        l.account_id,
                        a.login AS account_login,
                        COUNT(*) AS connections,
                        MIN(l.login_time) AS first_seen,
                        MAX(l.login_time) AS last_seen
                 FROM `loginlog2` l
                 LEFT JOIN `account`.`account` a ON a.id = l.account_id
                 ' . $where . '
                 GROUP BY l.ip, l.account_id, a.login
                 ORDER BY last_seen DESC
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    /**
     * @return array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null
     * }
     */
    private function requireLog(string $id): array
    {
        $log = LogCatalog::get($id);

        if ($log === null) {
            throw new \InvalidArgumentException('admin.logs.unknown');
        }

        return $log;
    }

    /**
     * @param list<string> $playerColumns
     * @return array{0: string, 1: list<mixed>}
     */
    private function characterWhere(array $playerColumns, int $playerId, string $playerName): array
    {
        $clauses = [];
        $params = [];

        foreach ($playerColumns as $column) {
            $quoted = '`' . Database::quoteIdentifier($column) . '`';

            if (in_array($column, ['pid', 'player_id', 'who'], true)) {
                $clauses[] = $quoted . ' = ?';
                $params[] = $playerId;
                continue;
            }

            if (in_array($column, ['name', 'username', 'old_name', 'new_name'], true) && $playerName !== '') {
                $clauses[] = $quoted . ' = ?';
                $params[] = $playerName;
            }
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' OR ', $clauses), $params];
    }

    /**
     * @param array{
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null
     * } $log
     * @param array{q?: string, from?: string, to?: string} $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(array $log, array $filters): array
    {
        $clauses = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '' && $log['search'] !== []) {
            $escaped = $this->escapeLike($q);
            $ors = [];

            foreach ($log['search'] as $column) {
                $quoted = '`' . Database::quoteIdentifier($column) . '`';
                $ors[] = 'CAST(' . $quoted . ' AS CHAR) LIKE ?';
                $params[] = '%' . $escaped . '%';
            }

            $clauses[] = '(' . implode(' OR ', $ors) . ')';
        }

        $this->appendDateRange($clauses, $params, $log['dateColumn'], $filters);

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @param array{q?: string, from?: string, to?: string} $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function connectionWhere(array $filters): array
    {
        $clauses = ['l.ip IS NOT NULL', 'TRIM(l.ip) <> \'\''];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $escaped = $this->escapeLike($q);
            $clauses[] = '(l.ip LIKE ? OR CAST(l.account_id AS CHAR) LIKE ? OR a.login LIKE ?)';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }

        $this->appendDateRange($clauses, $params, 'l.login_time', $filters);

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @param list<string> $clauses
     * @param list<mixed> $params
     * @param array{from?: string, to?: string} $filters
     */
    private function appendDateRange(array &$clauses, array &$params, ?string $column, array $filters): void
    {
        if ($column === null || $column === '') {
            return;
        }

        $quoted = $this->qualifyColumn($column);
        $from = $this->normalizeDate((string) ($filters['from'] ?? ''), false);
        $to = $this->normalizeDate((string) ($filters['to'] ?? ''), true);

        if ($from !== null) {
            $clauses[] = $quoted . ' >= ?';
            $params[] = $from;
        }

        if ($to !== null) {
            $clauses[] = $quoted . ' <= ?';
            $params[] = $to;
        }
    }

    /**
     * @param array{columns: list<string>, dateColumn: string|null} $log
     */
    private function orderBy(array $log): string
    {
        if ($log['dateColumn'] !== null && $log['dateColumn'] !== '') {
            return '`' . Database::quoteIdentifier($log['dateColumn']) . '` DESC';
        }

        return '`' . Database::quoteIdentifier($log['columns'][0]) . '` DESC';
    }

    /**
     * @param list<string> $columns
     */
    private function selectList(array $columns): string
    {
        return implode(', ', array_map(
            fn (string $column): string => '`' . Database::quoteIdentifier($column) . '`',
            $columns,
        ));
    }

    private function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            [$alias, $name] = explode('.', $column, 2);

            return Database::quoteIdentifier($alias) . '.`' . Database::quoteIdentifier($name) . '`';
        }

        return '`' . Database::quoteIdentifier($column) . '`';
    }

    private function normalizeDate(string $value, bool $endOfDay): ?string
    {
        $value = trim($value);

        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false) {
            return null;
        }

        return $endOfDay ? $date->format('Y-m-d') . ' 23:59:59' : $date->format('Y-m-d') . ' 00:00:00';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sanitizeRows(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            unset($row['password'], $row['social_id'], $row['securitycode']);

            return $row;
        }, $rows));
    }
}
