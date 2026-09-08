<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ItemAwardRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
    }

    public function countForAdmin(?string $query = null, ?string $status = null): int
    {
        if (!$this->schemaTableExists('item_award')) {
            return 0;
        }

        [$where, $params] = $this->filterClause($query, $status);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `item_award` a' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null, ?string $status = null): array
    {
        if (!$this->schemaTableExists('item_award')) {
            return [];
        }

        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->filterClause($query, $status);

        $rows = $this->revealAll(
            $this->db()->fetchAll(
                'SELECT a.id, a.pid, a.login, a.vnum, a.count, a.given_time, a.taken_time,
                        a.item_id, a.why, a.socket0, a.socket1, a.socket2, a.mall
                 FROM `item_award` a
                 ' . $where . '
                 ORDER BY a.given_time DESC, a.id DESC
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params,
            ),
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'pid' => (int) ($row['pid'] ?? 0),
                'login' => (string) ($row['login'] ?? ''),
                'vnum' => (int) ($row['vnum'] ?? 0),
                'count' => (int) ($row['count'] ?? 0),
                'given_time' => $row['given_time'] ?? null,
                'taken_time' => $row['taken_time'] ?? null,
                'item_id' => $row['item_id'] !== null ? (int) $row['item_id'] : null,
                'why' => $row['why'] !== null ? (string) $row['why'] : null,
                'socket0' => (int) ($row['socket0'] ?? 0),
                'socket1' => (int) ($row['socket1'] ?? 0),
                'socket2' => (int) ($row['socket2'] ?? 0),
                'mall' => (int) ($row['mall'] ?? 0) === 1,
                'pending' => ($row['taken_time'] ?? null) === null,
            ];
        }, $rows);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        if (!$this->schemaTableExists('item_award')) {
            throw new \RuntimeException('admin.awards.unavailable');
        }

        $login = trim((string) ($input['login'] ?? ''));
        $pid = (int) ($input['pid'] ?? 0);
        $vnum = (int) ($input['vnum'] ?? 0);
        $count = max(1, (int) ($input['count'] ?? 1));
        $mall = (int) ($input['mall'] ?? 0) === 1 ? 1 : 0;
        $why = trim((string) ($input['why'] ?? ''));

        if ($login === '' || strlen($login) > 30) {
            throw new \InvalidArgumentException('admin.awards.invalid_login');
        }

        if ($vnum < 1) {
            throw new \InvalidArgumentException('admin.awards.invalid_vnum');
        }

        if (strlen($why) > 128) {
            throw new \InvalidArgumentException('admin.awards.invalid_why');
        }

        $this->db()->execute(
            'INSERT INTO `item_award`
             (pid, login, vnum, count, given_time, taken_time, item_id, why, socket0, socket1, socket2, mall)
             VALUES (?, ?, ?, ?, NOW(), NULL, NULL, ?, ?, ?, ?, ?)',
            [
                max(0, $pid),
                $login,
                $vnum,
                $count,
                $why !== '' ? $why : null,
                (int) ($input['socket0'] ?? 0),
                (int) ($input['socket1'] ?? 0),
                (int) ($input['socket2'] ?? 0),
                $mall,
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $rows = $this->listForAdmin(1, 1, (string) $id, null);

        if ($rows === []) {
            throw new \RuntimeException('admin.awards.create_failed');
        }

        return $rows[0];
    }

    public function deletePending(int $id): bool
    {
        if ($id < 1 || !$this->schemaTableExists('item_award')) {
            return false;
        }

        return $this->db()->execute(
            'DELETE FROM `item_award` WHERE id = ? AND taken_time IS NULL',
            [$id],
        ) > 0;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function filterClause(?string $query, ?string $status): array
    {
        $clauses = [];
        $params = [];

        if ($status === 'pending') {
            $clauses[] = 'a.taken_time IS NULL';
        } elseif ($status === 'taken') {
            $clauses[] = 'a.taken_time IS NOT NULL';
        }

        if ($query !== null && $query !== '') {
            if (ctype_digit($query)) {
                $clauses[] = '(a.id = ? OR a.pid = ? OR a.vnum = ? OR a.login LIKE ? OR a.why LIKE ?)';
                $like = '%' . $query . '%';
                array_push($params, (int) $query, (int) $query, (int) $query, $like, $like);
            } else {
                $clauses[] = '(a.login LIKE ? OR a.why LIKE ?)';
                $like = '%' . $query . '%';
                array_push($params, $like, $like);
            }
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
