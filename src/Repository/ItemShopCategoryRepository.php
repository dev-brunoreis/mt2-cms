<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ItemShopCategoryRepository extends Repository
{
    public const MAX_DEPTH = 6;

    protected function database(): string
    {
        return 'cms';
    }

    /**
     * Nested category tree for the Magento-style admin panel.
     *
     * @return list<array<string, mixed>>
     */
    public function treeForAdmin(): array
    {
        return $this->buildTree($this->listFlat());
    }

    /**
     * Enabled nested tree for the public shop nav.
     *
     * @return list<array<string, mixed>>
     */
    public function treeEnabled(): array
    {
        $rows = array_values(array_filter(
            $this->listFlat(),
            static fn (array $row): bool => (int) ($row['enabled'] ?? 0) === 1,
        ));

        return $this->buildTree($rows);
    }

    /**
     * Flat list with indentation labels for product selects.
     *
     * @return list<array<string, mixed>>
     */
    public function listAllForSelect(): array
    {
        $flat = [];
        $this->flattenTree($this->treeForAdmin(), $flat, 0);

        return $flat;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnabled(): array
    {
        $flat = [];
        $this->flattenTree($this->treeEnabled(), $flat, 0);

        return $flat;
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, c.enabled, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM item_shop_products p WHERE p.category_id = c.id) AS product_count,
                    (SELECT COUNT(*) FROM item_shop_categories ch WHERE ch.parent_id = c.id) AS child_count
             FROM item_shop_categories c
             WHERE c.id = ?
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
            'SELECT id, parent_id, name, slug, sort_order, enabled
             FROM item_shop_categories
             WHERE slug = ? AND enabled = 1
             LIMIT 1',
            [$slug],
        );
    }

    /**
     * Selected category plus all descendants (for public product filtering).
     *
     * @return list<int>
     */
    public function idWithDescendants(int $id): array
    {
        if ($id < 1) {
            return [];
        }

        $byParent = [];

        foreach ($this->listFlat() as $row) {
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $byParent[$parentId][] = (int) $row['id'];
        }

        $ids = [];
        $stack = [$id];

        while ($stack !== []) {
            $current = array_pop($stack);
            $ids[] = $current;

            foreach ($byParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        $name = $this->assertName((string) ($input['name'] ?? ''));
        $slug = $this->assertSlug((string) ($input['slug'] ?? ''), $name);
        $parentId = $this->normalizeParentId($input['parent_id'] ?? null);
        $enabled = (int) ($input['enabled'] ?? 0) === 1 ? 1 : 0;

        if ($this->slugExists($slug)) {
            throw new \InvalidArgumentException('admin.item_shop.categories.slug_exists');
        }

        if ($parentId !== null) {
            $this->assertParentExists($parentId);
            $this->assertDepthAllowed($parentId, null);
        }

        $sortOrder = array_key_exists('sort_order', $input)
            ? (int) $input['sort_order']
            : $this->nextSortOrder($parentId);

        $this->db()->execute(
            'INSERT INTO item_shop_categories (parent_id, name, slug, sort_order, enabled)
             VALUES (?, ?, ?, ?, ?)',
            [$parentId, $name, $slug, $sortOrder, $enabled],
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
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new \RuntimeException('admin.item_shop.categories.not_found');
        }

        $name = $this->assertName((string) ($input['name'] ?? ''));
        $slug = $this->assertSlug((string) ($input['slug'] ?? ''), $name);
        $parentId = array_key_exists('parent_id', $input)
            ? $this->normalizeParentId($input['parent_id'])
            : ($existing['parent_id'] !== null ? (int) $existing['parent_id'] : null);
        $enabled = (int) ($input['enabled'] ?? 0) === 1 ? 1 : 0;
        $sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order'] ?? 0);

        if ($this->slugExists($slug, $id)) {
            throw new \InvalidArgumentException('admin.item_shop.categories.slug_exists');
        }

        if ($parentId === $id) {
            throw new \InvalidArgumentException('admin.item_shop.categories.invalid_parent');
        }

        if ($parentId !== null) {
            $this->assertParentExists($parentId);

            if ($this->isDescendantOf($parentId, $id)) {
                throw new \InvalidArgumentException('admin.item_shop.categories.invalid_parent');
            }

            $this->assertDepthAllowed($parentId, $id);
        }

        $this->db()->execute(
            'UPDATE item_shop_categories
             SET parent_id = ?, name = ?, slug = ?, sort_order = ?, enabled = ?
             WHERE id = ?',
            [$parentId, $name, $slug, $sortOrder, $enabled, $id],
        );

        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('admin.item_shop.categories.not_found');
        }

        return $row;
    }

    /**
     * Move a category under a parent at a sibling position (0-based).
     */
    public function move(int $id, ?int $parentId, int $position): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new \RuntimeException('admin.item_shop.categories.not_found');
        }

        $parentId = $this->normalizeParentId($parentId);

        if ($parentId === $id) {
            throw new \InvalidArgumentException('admin.item_shop.categories.invalid_parent');
        }

        if ($parentId !== null) {
            $this->assertParentExists($parentId);

            if ($this->isDescendantOf($parentId, $id)) {
                throw new \InvalidArgumentException('admin.item_shop.categories.invalid_parent');
            }

            $this->assertDepthAllowed($parentId, $id);
        }

        $siblings = $this->siblingIds($parentId);
        $siblings = array_values(array_filter($siblings, static fn (int $siblingId): bool => $siblingId !== $id));
        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$id]);

        $this->db()->beginTransaction();

        try {
            $this->db()->execute(
                'UPDATE item_shop_categories SET parent_id = ? WHERE id = ?',
                [$parentId, $id],
            );

            foreach ($siblings as $index => $siblingId) {
                $this->db()->execute(
                    'UPDATE item_shop_categories SET sort_order = ? WHERE id = ?',
                    [$index, $siblingId],
                );
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            throw $e;
        }
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

        $childCount = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM item_shop_categories WHERE parent_id = ?',
            [$id],
        );

        if ($childCount > 0) {
            throw new \RuntimeException('admin.item_shop.categories.has_children');
        }

        return $this->db()->execute(
            'DELETE FROM item_shop_categories WHERE id = ?',
            [$id],
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listFlat(): array
    {
        return $this->db()->fetchAll(
            'SELECT c.id, c.parent_id, c.name, c.slug, c.sort_order, c.enabled, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM item_shop_products p WHERE p.category_id = c.id) AS product_count
             FROM item_shop_categories c
             ORDER BY c.sort_order ASC, c.id ASC',
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $rows): array
    {
        /** @var array<int, array<string, mixed>> $byId */
        $byId = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $row['id'] = $id;
            $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $row['sort_order'] = (int) $row['sort_order'];
            $row['enabled'] = (int) $row['enabled'];
            $row['product_count'] = (int) ($row['product_count'] ?? 0);
            $row['children'] = [];
            $byId[$id] = $row;
        }

        $roots = [];

        foreach ($byId as $id => &$node) {
            $parentId = $node['parent_id'];

            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$node;
            } else {
                $node['parent_id'] = $parentId !== null && !isset($byId[$parentId]) ? null : $parentId;
                $roots[] = &$node;
            }
        }

        unset($node);

        return $roots;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $out
     */
    private function flattenTree(array $nodes, array &$out, int $depth): void
    {
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $node['depth'] = $depth;
            $node['label'] = str_repeat('— ', $depth) . (string) $node['name'];
            $out[] = $node;
            $this->flattenTree($children, $out, $depth + 1);
        }
    }

    /**
     * @return list<int>
     */
    private function siblingIds(?int $parentId): array
    {
        if ($parentId === null) {
            $rows = $this->db()->fetchAll(
                'SELECT id FROM item_shop_categories
                 WHERE parent_id IS NULL
                 ORDER BY sort_order ASC, id ASC',
            );
        } else {
            $rows = $this->db()->fetchAll(
                'SELECT id FROM item_shop_categories
                 WHERE parent_id = ?
                 ORDER BY sort_order ASC, id ASC',
                [$parentId],
            );
        }

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function nextSortOrder(?int $parentId): int
    {
        if ($parentId === null) {
            return (int) $this->db()->fetchColumn(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM item_shop_categories WHERE parent_id IS NULL',
            );
        }

        return (int) $this->db()->fetchColumn(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM item_shop_categories WHERE parent_id = ?',
            [$parentId],
        );
    }

    private function normalizeParentId(mixed $parentId): ?int
    {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return null;
        }

        return (int) $parentId;
    }

    private function assertParentExists(int $parentId): void
    {
        if ($this->findById($parentId) === null) {
            throw new \InvalidArgumentException('admin.item_shop.categories.invalid_parent');
        }
    }

    private function assertDepthAllowed(int $parentId, ?int $movingId): void
    {
        $parentDepth = $this->depthOf($parentId);
        $subtreeHeight = $movingId !== null ? $this->subtreeHeight($movingId) : 0;

        if ($parentDepth + 1 + $subtreeHeight > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('admin.item_shop.categories.too_deep');
        }
    }

    private function depthOf(int $id): int
    {
        $depth = 0;
        $current = $this->findById($id);

        while ($current !== null && $current['parent_id'] !== null) {
            $depth++;

            if ($depth > self::MAX_DEPTH) {
                break;
            }

            $current = $this->findById((int) $current['parent_id']);
        }

        return $depth;
    }

    private function subtreeHeight(int $id): int
    {
        $byParent = [];

        foreach ($this->listFlat() as $row) {
            $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $byParent[$parentId][] = (int) $row['id'];
        }

        $walk = function (int $nodeId) use (&$walk, $byParent): int {
            $max = 0;

            foreach ($byParent[$nodeId] ?? [] as $childId) {
                $max = max($max, 1 + $walk($childId));
            }

            return $max;
        };

        return $walk($id);
    }

    private function isDescendantOf(int $possibleDescendant, int $ancestorId): bool
    {
        return in_array($possibleDescendant, $this->idWithDescendants($ancestorId), true)
            && $possibleDescendant !== $ancestorId;
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
