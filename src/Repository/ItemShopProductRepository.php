<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class ItemShopProductRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/item-shop', 'admin.item_shop.products')
            ->orderBy([
                'id' => 'p.id',
                'vnum' => 'p.vnum',
                'category_name' => 'c.name',
                'count' => 'p.count',
                'price' => 'p.price',
                'enabled' => 'p.enabled',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.item_shop.products.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/item-shop/{id}'],
                ['key' => 'vnum', 'label' => 'admin.item_shop.products.vnum', 'sort' => 'vnum', 'type' => 'number'],
                ['key' => 'item_name', 'label' => 'admin.item_shop.products.item', 'type' => 'text'],
                ['key' => 'category_name', 'label' => 'admin.item_shop.products.category', 'sort' => 'category_name', 'type' => 'text'],
                ['key' => 'count', 'label' => 'admin.item_shop.products.count_label', 'sort' => 'count', 'type' => 'number'],
                ['key' => 'price', 'label' => 'admin.item_shop.products.price', 'sort' => 'price', 'type' => 'number'],
                ['key' => 'enabled', 'label' => 'admin.item_shop.products.enabled', 'sort' => 'enabled', 'type' => 'badge', 'badgeMap' => [
                    '1' => ['class' => 'admin-badge-ok', 'label' => 'admin.item_shop.enabled_yes'],
                    '0' => ['class' => 'admin-badge-muted', 'label' => 'admin.item_shop.enabled_no'],
                ]],
            ])
            ->filters([
                ['key' => 'category_id', 'label' => 'admin.item_shop.products.category', 'type' => 'select', 'options' => []],
            ])
            ->massActions('/admin/item-shop/mass', [
                ['id' => 'enable', 'label' => 'admin.grid.enable'],
                ['id' => 'disable', 'label' => 'admin.grid.disable'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.item_shop.products.confirm_mass_delete'],
            ]);
    }

    private const MAX_COUNT = 200;

    protected function database(): string
    {
        return 'cms';
    }

    public function countForAdmin(?string $query = null, ?int $categoryId = null): int
    {
        $filters = [];

        if ($categoryId !== null && $categoryId > 0) {
            $filters['category_id'] = (string) $categoryId;
        }

        return $this->countForGrid(new GridQuery($query, 1, 20, 'id', 'asc', $filters));
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

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
        $filters = [];

        if ($categoryId !== null && $categoryId > 0) {
            $filters['category_id'] = (string) $categoryId;
        }

        return $this->listForGrid(new GridQuery($query, $page, $perPage, 'id', 'asc', $filters));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'p.sort_order ASC, p.id ASC');

        return $this->db()->fetchAll(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order, p.created_at, p.updated_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id' . $where . $order . '
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
     * @return list<array<string, mixed>>
     */
    public function listByCategoryId(int $categoryId): array
    {
        if ($categoryId < 1) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT p.id, p.category_id, p.vnum, p.count, p.price, p.socket0, p.socket1, p.socket2,
                    p.enabled, p.sort_order, p.created_at, p.updated_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM item_shop_products p
             INNER JOIN item_shop_categories c ON c.id = p.category_id
             WHERE p.category_id = ?
             ORDER BY p.sort_order ASC, p.id ASC',
            [$categoryId],
        );
    }

    /**
     * @return list<int>
     */
    public function vnumsInCategory(int $categoryId): array
    {
        if ($categoryId < 1) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT DISTINCT vnum FROM item_shop_products WHERE category_id = ?',
            [$categoryId],
        );

        return array_map(static fn (array $row): int => (int) $row['vnum'], $rows);
    }

    /**
     * @param list<array{vnum: int, count?: int, price: int}> $items
     * @return list<array<string, mixed>>
     */
    public function createManyForCategory(int $categoryId, array $items): array
    {
        if ($categoryId < 1) {
            throw new \InvalidArgumentException('admin.item_shop.products.invalid_category');
        }

        if ($items === []) {
            throw new \InvalidArgumentException('admin.item_shop.categories.no_items_selected');
        }

        $created = [];
        $sortBase = (int) $this->db()->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM item_shop_products WHERE category_id = ?',
            [$categoryId],
        );

        $this->db()->beginTransaction();

        try {
            foreach ($items as $index => $item) {
                $created[] = $this->create([
                    'category_id' => $categoryId,
                    'vnum' => (int) ($item['vnum'] ?? 0),
                    'count' => (int) ($item['count'] ?? 1),
                    'price' => (int) ($item['price'] ?? 0),
                    'socket0' => (int) ($item['socket0'] ?? 0),
                    'socket1' => (int) ($item['socket1'] ?? 0),
                    'socket2' => (int) ($item['socket2'] ?? 0),
                    'enabled' => 1,
                    'sort_order' => $sortBase + $index,
                ]);
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            throw $e;
        }

        return $created;
    }

    public function updatePriceAndCount(int $id, int $price, int $count): array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new \RuntimeException('admin.item_shop.products.not_found');
        }

        return $this->update($id, array_merge($existing, [
            'price' => $price,
            'count' => $count,
        ]));
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
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];

        $categoryId = $query->filter('category_id');

        if ($categoryId !== '' && ctype_digit($categoryId) && (int) $categoryId > 0) {
            $clauses[] = 'p.category_id = ?';
            $params[] = (int) $categoryId;
        }

        if ($query->q !== null && $query->q !== '') {
            if (ctype_digit($query->q)) {
                $clauses[] = '(p.id = ? OR p.vnum = ? OR c.name LIKE ?)';
                $like = '%' . $query->q . '%';
                array_push($params, (int) $query->q, (int) $query->q, $like);
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
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
