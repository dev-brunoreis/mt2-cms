<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class ItemShopOrderRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/store/orders', 'admin.item_shop.orders')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'count' => 'count',
                'price' => 'price',
                'status' => 'status',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.item_shop.orders.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'account_login', 'label' => 'admin.item_shop.orders.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'item_name', 'label' => 'admin.item_shop.orders.item', 'type' => 'text'],
                ['key' => 'count', 'label' => 'admin.item_shop.orders.count_label', 'sort' => 'count', 'type' => 'number'],
                ['key' => 'price', 'label' => 'admin.item_shop.orders.price', 'sort' => 'price', 'type' => 'number'],
                ['key' => 'status', 'label' => 'admin.item_shop.orders.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'pending' => ['class' => 'admin-badge-warn', 'label' => 'admin.item_shop.orders.status_pending'],
                    'completed' => ['class' => 'admin-badge-ok', 'label' => 'admin.item_shop.orders.status_completed'],
                    'failed' => ['class' => 'admin-badge-danger', 'label' => 'admin.item_shop.orders.status_failed'],
                ]],
                ['key' => 'created_at', 'label' => 'admin.item_shop.orders.created', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.item_shop.orders.status', 'type' => 'select', 'options' => [
                    'pending' => 'admin.item_shop.orders.status_pending',
                    'completed' => 'admin.item_shop.orders.status_completed',
                    'failed' => 'admin.item_shop.orders.status_failed',
                ]],
            ]);
    }

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
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'id DESC');

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
        $clauses = [];
        $params = [];

        $status = $query->filter('status');

        if ($status !== '' && in_array($status, ['pending', 'completed', 'failed'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        if ($query->q !== null && $query->q !== '') {
            if (ctype_digit($query->q)) {
                $clauses[] = '(id = ? OR account_id = ? OR product_id = ? OR vnum = ? OR account_login LIKE ?)';
                $like = '%' . $query->q . '%';
                array_push($params, (int) $query->q, (int) $query->q, (int) $query->q, (int) $query->q, $like);
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
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
