<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\AwardsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class ItemAwardRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'player';
    }

    public function countForGrid(GridQuery $query): int
    {
        if (!$this->schemaTableExists('item_award')) {
            return 0;
        }

        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `item_award` a' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        if (!$this->schemaTableExists('item_award')) {
            return [];
        }

        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, AwardsGrid::definition()->sortMap(), 'a.given_time DESC, a.id DESC');

        $rows = $this->revealAll(
            $this->db()->fetchAll(
                'SELECT a.id, a.pid, a.login, a.vnum, a.count, a.given_time, a.taken_time,
                        a.item_id, a.why, a.socket0, a.socket1, a.socket2, a.mall
                 FROM `item_award` a' . $where . $order . '
                 LIMIT ? OFFSET ?',
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
                'status' => ($row['taken_time'] ?? null) === null ? 'pending' : 'taken',
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
        $rows = $this->listForGrid(new GridQuery((string) $id, 1, 1, 'id', 'desc', []));

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
     * Deliver a mall item award for the item shop (pid=0, mall=1).
     *
     * @param array{
     *   login: string,
     *   vnum: int,
     *   count: int,
     *   socket0?: int,
     *   socket1?: int,
     *   socket2?: int,
     *   why?: string
     * } $input
     */
    public function createMallAward(array $input): int
    {
        if (!$this->schemaTableExists('item_award')) {
            throw new \RuntimeException('shop.awards_unavailable');
        }

        $login = trim((string) ($input['login'] ?? ''));
        $vnum = (int) ($input['vnum'] ?? 0);
        $count = max(1, (int) ($input['count'] ?? 1));
        $why = trim((string) ($input['why'] ?? ''));

        if ($login === '' || strlen($login) > 30) {
            throw new \InvalidArgumentException('admin.awards.invalid_login');
        }

        if ($vnum < 1) {
            throw new \InvalidArgumentException('admin.awards.invalid_vnum');
        }

        if ($count > 200) {
            throw new \InvalidArgumentException('admin.awards.invalid_count');
        }

        if (strlen($why) > 128) {
            throw new \InvalidArgumentException('admin.awards.invalid_why');
        }

        $this->db()->execute(
            'INSERT INTO `item_award`
             (pid, login, vnum, count, given_time, taken_time, item_id, why, socket0, socket1, socket2, mall)
             VALUES (0, ?, ?, ?, NOW(), NULL, NULL, ?, ?, ?, ?, 1)',
            [
                $login,
                $vnum,
                $count,
                $why !== '' ? $why : null,
                (int) ($input['socket0'] ?? 0),
                (int) ($input['socket1'] ?? 0),
                (int) ($input['socket2'] ?? 0),
            ],
        );

        $id = (int) $this->db()->lastInsertId();

        if ($id < 1) {
            throw new \RuntimeException('admin.awards.create_failed');
        }

        return $id;
    }

    public function findPendingIdByWhy(string $why): ?int
    {
        $why = trim($why);

        if ($why === '' || !$this->schemaTableExists('item_award')) {
            return null;
        }

        $id = $this->db()->fetchColumn(
            'SELECT id FROM `item_award`
             WHERE why = ? AND taken_time IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [$why],
        );

        return $id !== null ? (int) $id : null;
    }

    public function findPendingIdByWhyPrefix(string $whyPrefix): ?int
    {
        $whyPrefix = trim($whyPrefix);

        if ($whyPrefix === '' || !$this->schemaTableExists('item_award')) {
            return null;
        }

        $id = $this->db()->fetchColumn(
            'SELECT id FROM `item_award`
             WHERE (why = ? OR why = ?) AND taken_time IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [$whyPrefix, $whyPrefix . ':ok'],
        );

        return $id !== null ? (int) $id : null;
    }

    public function markShopAwardPaid(int $id, string $whyPrefix): bool
    {
        if ($id < 1 || !$this->schemaTableExists('item_award')) {
            return false;
        }

        $whyPrefix = trim($whyPrefix);

        return $this->db()->execute(
            'UPDATE `item_award`
             SET why = ?
             WHERE id = ? AND taken_time IS NULL AND why = ?',
            [$whyPrefix . ':ok', $id, $whyPrefix],
        ) > 0;
    }

    public function isShopAwardPaid(int $id, string $whyPrefix): bool
    {
        if ($id < 1 || !$this->schemaTableExists('item_award')) {
            return false;
        }

        $why = $this->db()->fetchColumn(
            'SELECT why FROM `item_award` WHERE id = ? LIMIT 1',
            [$id],
        );

        return is_string($why) && $why === trim($whyPrefix) . ':ok';
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [
            // Item-shop purchases use why = shop:{orderId}[:ok]; those belong under Shop orders.
            "(a.why IS NULL OR a.why NOT LIKE 'shop:%')",
        ];
        $params = [];

        $status = $query->filter('status');

        if ($status === 'pending') {
            $clauses[] = 'a.taken_time IS NULL';
        } elseif ($status === 'taken') {
            $clauses[] = 'a.taken_time IS NOT NULL';
        }

        if ($query->q !== null && $query->q !== '') {
            if (ctype_digit($query->q)) {
                $clauses[] = '(a.id = ? OR a.pid = ? OR a.vnum = ? OR a.login LIKE ? OR a.why LIKE ?)';
                $like = '%' . $query->q . '%';
                array_push($params, (int) $query->q, (int) $query->q, (int) $query->q, $like, $like);
            } else {
                $clauses[] = '(a.login LIKE ? OR a.why LIKE ?)';
                $like = '%' . $query->q . '%';
                array_push($params, $like, $like);
            }
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
