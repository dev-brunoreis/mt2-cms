<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ItemShopOrderRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function countForAdmin(?string $query = null, ?string $status = null): int
    {
        [$where, $params] = $this->adminWhere($query, $status);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_shop_orders' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null, ?string $status = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->adminWhere($query, $status);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db()->fetchAll(
            'SELECT id, account_id, account_login, product_id, vnum, count, price,
                    socket0, socket1, socket2, item_award_id, cash_debited, status, idempotency_key,
                    created_at, updated_at
             FROM item_shop_orders' . $where . '
             ORDER BY id DESC
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT id, account_id, account_login, product_id, vnum, count, price,
                    socket0, socket1, socket2, item_award_id, cash_debited, status, idempotency_key,
                    created_at, updated_at
             FROM item_shop_orders
             WHERE id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        return $this->db()->fetch(
            'SELECT id, account_id, account_login, product_id, vnum, count, price,
                    socket0, socket1, socket2, item_award_id, cash_debited, status, idempotency_key,
                    created_at, updated_at
             FROM item_shop_orders
             WHERE idempotency_key = ?
             LIMIT 1',
            [$key],
        );
    }

    /**
     * @param array{
     *   account_id: int,
     *   account_login: string,
     *   product_id: int,
     *   vnum: int,
     *   count: int,
     *   price: int,
     *   socket0: int,
     *   socket1: int,
     *   socket2: int,
     *   idempotency_key: string
     * } $data
     */
    public function createPending(array $data): array
    {
        $this->db()->execute(
            'INSERT INTO item_shop_orders
             (account_id, account_login, product_id, vnum, count, price,
              socket0, socket1, socket2, item_award_id, cash_debited, status, idempotency_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0, ?, ?)',
            [
                $data['account_id'],
                $data['account_login'],
                $data['product_id'],
                $data['vnum'],
                $data['count'],
                $data['price'],
                $data['socket0'],
                $data['socket1'],
                $data['socket2'],
                'pending',
                $data['idempotency_key'],
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('shop.order_create_failed');
        }

        return $row;
    }

    public function attachAward(int $id, int $itemAwardId): void
    {
        $this->db()->execute(
            'UPDATE item_shop_orders
             SET item_award_id = ?
             WHERE id = ? AND status = ? AND item_award_id IS NULL',
            [$itemAwardId, $id, 'pending'],
        );
    }

    public function markCashDebited(int $id): void
    {
        $this->db()->execute(
            'UPDATE item_shop_orders
             SET cash_debited = 1
             WHERE id = ? AND status = ?',
            [$id, 'pending'],
        );
    }

    public function markCompleted(int $id, int $itemAwardId): void
    {
        $this->db()->execute(
            'UPDATE item_shop_orders
             SET status = ?, item_award_id = ?, cash_debited = 1
             WHERE id = ? AND status = ?',
            ['completed', $itemAwardId, $id, 'pending'],
        );
    }

    public function markFailed(int $id): void
    {
        $this->db()->execute(
            'UPDATE item_shop_orders
             SET status = ?
             WHERE id = ? AND status = ?',
            ['failed', $id, 'pending'],
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $query, ?string $status): array
    {
        $clauses = [];
        $params = [];

        if ($status !== null && in_array($status, ['pending', 'completed', 'failed'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        if ($query !== null && $query !== '') {
            if (ctype_digit($query)) {
                $clauses[] = '(id = ? OR account_id = ? OR product_id = ? OR vnum = ? OR account_login LIKE ?)';
                $like = '%' . $query . '%';
                array_push($params, (int) $query, (int) $query, (int) $query, (int) $query, $like);
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
                $clauses[] = 'account_login LIKE ?';
                $params[] = '%' . $escaped . '%';
            }
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
