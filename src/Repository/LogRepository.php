<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\Support\Database;

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
     *   itemColumns: list<string>,
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
                'itemColumns' => $log['itemColumns'],
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    /**
     * Recent rows from log tables that identify this item instance.
     *
     * @return list<array{
     *   id: string,
     *   label: string,
     *   columns: list<string>,
     *   dateColumn: string|null,
     *   itemColumns: list<string>,
     *   rows: list<array<string, mixed>>
     * }>
     */
    public function listForItem(int $itemId, int $limit = 50): array
    {
        if ($itemId < 1) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $groups = [];

        foreach (LogCatalog::forItem() as $log) {
            if (!$this->tableExists($log['table'])) {
                continue;
            }

            [$where, $params] = $this->itemWhere($log['itemColumns'], $itemId);

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
                'itemColumns' => $log['itemColumns'],
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    public function countForGrid(string $id, GridQuery $query): int
    {
        return $this->countLogTable($id, $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(string $id, GridQuery $query): array
    {
        return $this->listLogTable($id, $query);
    }

    public function countConnectionsForGrid(GridQuery $query): int
    {
        return $this->countConnectionIps($query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConnectionsForGrid(GridQuery $query): array
    {
        return $this->listConnectionIps($query);
    }

    /**
     * @param array{
     *   columns: list<string>,
     *   dateColumn: string|null
     * } $log
     */
    private function countLogTable(string $id, GridQuery $query): int
    {
        $log = $this->requireLog($id);

        if (!$this->tableExists($log['table'])) {
            return 0;
        }

        [$where, $params] = $this->adminWhere($log, $query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . Database::quoteIdentifier($log['table']) . '`' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLogTable(string $id, GridQuery $query): array
    {
        $log = $this->requireLog($id);

        if (!$this->tableExists($log['table'])) {
            return [];
        }

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->adminWhere($log, $query);
        $params[] = $perPage;
        $params[] = $offset;

        $order = $this->orderBy($log, $query);

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

    public function countConnectionIps(GridQuery $query): int
    {
        if (!$this->tableExists('loginlog2')) {
            return 0;
        }

        [$where, $params, $having, $havingParams] = $this->connectionFilters($query);
        $params = array_merge($params, $havingParams);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM (
                SELECT l.ip, l.account_id
                FROM `loginlog2` l
                LEFT JOIN `account`.`account` a ON a.id = l.account_id
                ' . $where . '
                GROUP BY l.ip, l.account_id
                ' . $having . '
             ) AS ip_rows',
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConnectionIps(GridQuery $query): array
    {
        if (!$this->tableExists('loginlog2')) {
            return [];
        }

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params, $having, $havingParams] = $this->connectionFilters($query);
        $params = array_merge($params, $havingParams);
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
                 ' . $having . '
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
     * @param list<string> $itemColumns
     * @return array{0: string, 1: list<mixed>}
     */
    private function itemWhere(array $itemColumns, int $itemId): array
    {
        $clauses = [];
        $params = [];

        foreach ($itemColumns as $column) {
            $clauses[] = '`' . Database::quoteIdentifier($column) . '` = ?';
            $params[] = $itemId;
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' OR ', $clauses), $params];
    }

    /**
     * @param array{
     *   columns: list<string>,
     *   dateColumn: string|null
     * } $log
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(array $log, GridQuery $query): array
    {
        $clauses = [];
        $params = [];
        $dateColumns = array_intersect($log['columns'], [
            'time', 'date', 'login_time', 'logout_time', 'start_time', 'end_time', 'first_seen', 'last_seen',
        ]);

        if (in_array('date', $log['columns'], true)) {
            $dateColumns = array_values(array_diff($dateColumns, ['time']));
        }

        if (is_string($log['dateColumn']) && $log['dateColumn'] !== '') {
            $dateColumns[] = $log['dateColumn'];
        }

        foreach ($query->filters as $key => $value) {
            if ($value === '' || $key === 'from' || $key === 'to' || !in_array($key, $log['columns'], true)) {
                continue;
            }

            $quoted = '`' . Database::quoteIdentifier($key) . '`';

            if (in_array($key, $dateColumns, true)) {
                $clauses[] = 'DATE(' . $quoted . ') = ?';
                $params[] = $value;
                continue;
            }

            $clauses[] = 'CAST(' . $quoted . ' AS CHAR) LIKE ?';
            $params[] = '%' . $this->escapeLike($value) . '%';
        }

        $this->appendDateRange($clauses, $params, $log['dateColumn'], $query);

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @return array{0: string, 1: list<mixed>, 2: string, 3: list<mixed>}
     */
    private function connectionFilters(GridQuery $query): array
    {
        $clauses = ['l.ip IS NOT NULL', 'TRIM(l.ip) <> \'\''];
        $params = [];

        $ip = $query->filter('ip');

        if ($ip !== '') {
            $clauses[] = 'l.ip LIKE ?';
            $params[] = '%' . $this->escapeLike($ip) . '%';
        }

        $accountId = $query->filter('account_id');

        if ($accountId !== '') {
            if (preg_match('/^-?\d+$/', $accountId) === 1) {
                $clauses[] = 'l.account_id = ?';
                $params[] = (int) $accountId;
            } else {
                $clauses[] = '(CAST(l.account_id AS CHAR) LIKE ? OR a.login LIKE ?)';
                $like = '%' . $this->escapeLike($accountId) . '%';
                $params[] = $like;
                $params[] = $like;
            }
        }

        $this->appendDateRange($clauses, $params, 'l.login_time', $query);

        $having = [];
        $havingParams = [];
        $connections = $query->filter('connections');

        if ($connections !== '') {
            if (preg_match('/^-?\d+$/', $connections) === 1) {
                $having[] = 'COUNT(*) = ?';
                $havingParams[] = (int) $connections;
            } else {
                $having[] = 'CAST(COUNT(*) AS CHAR) LIKE ?';
                $havingParams[] = '%' . $this->escapeLike($connections) . '%';
            }
        }

        $firstSeen = $query->filter('first_seen');

        if ($firstSeen !== '') {
            $having[] = 'DATE(MIN(l.login_time)) = ?';
            $havingParams[] = $firstSeen;
        }

        $lastSeen = $query->filter('last_seen');

        if ($lastSeen !== '') {
            $having[] = 'DATE(MAX(l.login_time)) = ?';
            $havingParams[] = $lastSeen;
        }

        return [
            ' WHERE ' . implode(' AND ', $clauses),
            $params,
            $having === [] ? '' : ' HAVING ' . implode(' AND ', $having),
            $havingParams,
        ];
    }

    /**
     * @param list<string> $clauses
     * @param list<mixed> $params
     */
    private function appendDateRange(array &$clauses, array &$params, ?string $column, GridQuery $query): void
    {
        if ($column === null || $column === '') {
            return;
        }

        $quoted = $this->qualifyColumn($column);
        $from = $this->normalizeDate($query->filter('from'), false);
        $to = $this->normalizeDate($query->filter('to'), true);

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
    private function orderBy(array $log, ?GridQuery $query = null): string
    {
        $dir = $query !== null && $query->dir === 'asc' ? 'ASC' : 'DESC';
        $sort = $query?->sort ?? '';

        if ($sort !== '' && in_array($sort, $log['columns'], true)) {
            return '`' . Database::quoteIdentifier($sort) . '` ' . $dir;
        }

        if ($log['dateColumn'] !== null && $log['dateColumn'] !== '') {
            return '`' . Database::quoteIdentifier($log['dateColumn']) . '` ' . $dir;
        }

        return '`' . Database::quoteIdentifier($log['columns'][0]) . '` ' . $dir;
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
