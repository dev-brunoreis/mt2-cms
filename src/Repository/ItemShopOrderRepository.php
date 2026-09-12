<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\ItemShopOrdersGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class ItemShopOrderRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_shop_orders' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, ItemShopOrdersGrid::definition()->sortMap(), 'id DESC');

        return $this->db()->fetchAll(
            'SELECT id, account_id, account_login, product_id, vnum, count, price,
                    socket0, socket1, socket2, item_award_id, cash_debited, status, idempotency_key,
                    created_at, updated_at
             FROM item_shop_orders' . $where . $order . '
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
     * @return list<array<string, mixed>>
     */
    public function listByAccountId(int $accountId, int $limit = 50): array
    {
        if ($accountId < 1) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT id, product_id, vnum, count, price, status, created_at
             FROM item_shop_orders
             WHERE account_id = ?
             ORDER BY id DESC
             LIMIT ?',
            [$accountId, max(1, min(100, $limit))],
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::where($query, ItemShopOrdersGrid::definition()->filterSql());
    }
}
