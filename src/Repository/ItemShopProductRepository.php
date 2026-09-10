<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ItemShopProductRepository extends Repository
{
    private const MAX_COUNT = 200;

    protected function database(): string
    {
        return 'cms';
    }

    public function countForAdmin(?string $query = null, ?int $categoryId = null): int
    {
        [$where, $params] = $this->adminWhere($query, $categoryId);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*)
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null, ?int $categoryId = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->adminWhere($query, $categoryId);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db()->fetchAll(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order, p.created_at, p.updated_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id' . $where . '
             ORDER BY p.sort_order ASC, p.id ASC
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    /**
     * @param list<int>|null $categoryIds
     * @return list<array<string, mixed>>
     */
    public function listEnabled(?array $categoryIds = null): array
    {
        $clauses = ['p.enabled = 1', 'c.enabled = 1'];
        $params = [];

        if ($categoryIds !== null) {
            $categoryIds = array_values(array_filter(
                array_map('intval', $categoryIds),
                static fn (int $id): bool => $id > 0,
            ));

            if ($categoryIds === []) {
                return [];
            }

            $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
            $clauses[] = "p.category_id IN ({$placeholders})";
            array_push($params, ...$categoryIds);
        }

        $where = ' WHERE ' . implode(' AND ', $clauses);

        return $this->db()->fetchAll(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order,
                    c.name AS category_name, c.slug AS category_slug
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id' . $where . '
             ORDER BY c.sort_order ASC, p.sort_order ASC, p.id ASC',
            $params,
        );
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order, p.created_at, p.updated_at,
                    c.name AS category_name, c.slug AS category_slug, c.enabled AS category_enabled
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id
             WHERE p.id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function findEnabledForPurchase(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order,
                    c.name AS category_name, c.slug AS category_slug
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.enabled = 1 AND c.enabled = 1
             LIMIT 1',
            [$id],
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        $data = $this->normalizeInput($input);

        $this->db()->execute(
            'INSERT INTO item_shop_products
             (category_id, vnum, count, price, socket0, socket1, socket2, enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'],
                $data['vnum'],
                $data['count'],
                $data['price'],
                $data['socket0'],
                $data['socket1'],
                $data['socket2'],
                $data['enabled'],
                $data['sort_order'],
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('admin.item_shop.products.create_failed');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): array
    {
        if ($this->findById($id) === null) {
            throw new \RuntimeException('admin.item_shop.products.not_found');
        }

        $data = $this->normalizeInput($input);

        $this->db()->execute(
            'UPDATE item_shop_products
             SET category_id = ?, vnum = ?, count = ?, price = ?,
                 socket0 = ?, socket1 = ?, socket2 = ?, enabled = ?, sort_order = ?
             WHERE id = ?',
            [
                $data['category_id'],
                $data['vnum'],
                $data['count'],
                $data['price'],
                $data['socket0'],
                $data['socket1'],
                $data['socket2'],
                $data['enabled'],
                $data['sort_order'],
                $id,
            ],
        );

        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('admin.item_shop.products.not_found');
        }

        return $row;
    }

    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        return $this->db()->execute(
            'DELETE FROM item_shop_products WHERE id = ?',
            [$id],
        ) > 0;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   category_id: int,
     *   vnum: int,
     *   count: int,
     *   price: int,
     *   socket0: int,
     *   socket1: int,
     *   socket2: int,
     *   enabled: int,
     *   sort_order: int
     * }
     */
    private function normalizeInput(array $input): array
    {
        $categoryId = (int) ($input['category_id'] ?? 0);
        $vnum = (int) ($input['vnum'] ?? 0);
        $count = (int) ($input['count'] ?? 1);
        $price = (int) ($input['price'] ?? 0);
        $enabled = (int) ($input['enabled'] ?? 0) === 1 ? 1 : 0;
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if ($categoryId < 1) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_category');
        }

        $categoryExists = $this->db()->fetchColumn(
            'SELECT id FROM item_shop_categories WHERE id = ? LIMIT 1',
            [$categoryId],
        );

        if ($categoryExists === null) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_category');
        }

        if ($vnum < 1) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_vnum');
        }

        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_count');
        }

        if ($price < 1) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_price');
        }

        return [
            'category_id' => $categoryId,
            'vnum' => $vnum,
            'count' => $count,
            'price' => $price,
            'socket0' => (int) ($input['socket0'] ?? 0),
            'socket1' => (int) ($input['socket1'] ?? 0),
            'socket2' => (int) ($input['socket2'] ?? 0),
            'enabled' => $enabled,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $query, ?int $categoryId): array
    {
        $clauses = [];
        $params = [];

        if ($categoryId !== null && $categoryId > 0) {
            $clauses[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        if ($query !== null && $query !== '') {
            if (ctype_digit($query)) {
                $clauses[] = '(p.id = ? OR p.vnum = ? OR c.name LIKE ?)';
                $like = '%' . $query . '%';
                array_push($params, (int) $query, (int) $query, $like);
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
                $clauses[] = 'c.name LIKE ?';
                $params[] = '%' . $escaped . '%';
            }
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
