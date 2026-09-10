<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ItemShopCategoryRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function countForAdmin(?string $query = null): int
    {
        [$where, $params] = $this->adminWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_shop_categories' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->adminWhere($query);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db()->fetchAll(
            'SELECT c.id, c.name, c.slug, c.sort_order, c.enabled, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM item_shop_products p WHERE p.category_id = c.id) AS product_count
             FROM item_shop_categories c' . $where . '
             ORDER BY c.sort_order ASC, c.id ASC
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnabled(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, slug, sort_order, enabled
             FROM item_shop_categories
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAllForSelect(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, slug, enabled
             FROM item_shop_categories
             ORDER BY sort_order ASC, id ASC',
        );
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT id, name, slug, sort_order, enabled, created_at, updated_at
             FROM item_shop_categories
             WHERE id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function findEnabledBySlug(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return $this->db()->fetch(
            'SELECT id, name, slug, sort_order, enabled
             FROM item_shop_categories
             WHERE slug = ? AND enabled = 1
             LIMIT 1',
            [$slug],
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        $name = $this->assertName((string) ($input['name'] ?? ''));
        $slug = $this->assertSlug((string) ($input['slug'] ?? ''), $name);
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $enabled = (int) ($input['enabled'] ?? 0) === 1 ? 1 : 0;

        if ($this->slugExists($slug)) {
            throw new \InvalidArgumentException('admin.item_shop.categories.slug_exists');
        }

        $this->db()->execute(
            'INSERT INTO item_shop_categories (name, slug, sort_order, enabled)
             VALUES (?, ?, ?, ?)',
            [$name, $slug, $sortOrder, $enabled],
        );

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('admin.item_shop.categories.create_failed');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): array
    {
        if ($this->findById($id) === null) {
            throw new \RuntimeException('admin.item_shop.categories.not_found');
        }

        $name = $this->assertName((string) ($input['name'] ?? ''));
        $slug = $this->assertSlug((string) ($input['slug'] ?? ''), $name);
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $enabled = (int) ($input['enabled'] ?? 0) === 1 ? 1 : 0;

        if ($this->slugExists($slug, $id)) {
            throw new \InvalidArgumentException('admin.item_shop.categories.slug_exists');
        }

        $this->db()->execute(
            'UPDATE item_shop_categories
             SET name = ?, slug = ?, sort_order = ?, enabled = ?
             WHERE id = ?',
            [$name, $slug, $sortOrder, $enabled, $id],
        );

        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('admin.item_shop.categories.not_found');
        }

        return $row;
    }

    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $productCount = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_shop_products WHERE category_id = ?',
            [$id],
        );

        if ($productCount > 0) {
            throw new \RuntimeException('admin.item_shop.categories.has_products');
        }

        return $this->db()->execute(
            'DELETE FROM item_shop_categories WHERE id = ?',
            [$id],
        ) > 0;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $query): array
    {
        if ($query === null || $query === '') {
            return ['', []];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        return [
            ' WHERE name LIKE ? OR slug LIKE ?',
            ['%' . $escaped . '%', '%' . $escaped . '%'],
        ];
    }

    private function slugExists(string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            return $this->db()->fetchColumn(
                'SELECT id FROM item_shop_categories WHERE slug = ? LIMIT 1',
                [$slug],
            ) !== null;
        }

        return $this->db()->fetchColumn(
            'SELECT id FROM item_shop_categories WHERE slug = ? AND id != ? LIMIT 1',
            [$slug, $exceptId],
        ) !== null;
    }

    private function assertName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || strlen($name) > 120) {
            throw new \InvalidArgumentException('admin.item_shop.categories.invalid_name');
        }

        return $name;
    }

    private function assertSlug(string $slug, string $fallbackName): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            $slug = $this->slugify($fallbackName);
        } else {
            $slug = $this->slugify($slug);
        }

        if ($slug === '' || strlen($slug) > 140) {
            throw new \InvalidArgumentException('admin.item_shop.categories.invalid_slug');
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }
}
