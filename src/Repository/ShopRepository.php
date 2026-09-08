<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class ShopRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
    }

    public function countForAdmin(?string $query = null): int
    {
        if (!$this->schemaTableExists('shop')) {
            return 0;
        }

        [$where, $params] = $this->searchClause($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `shop` s' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null): array
    {
        if (!$this->schemaTableExists('shop')) {
            return [];
        }

        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->searchClause($query);
        $itemJoin = $this->schemaTableExists('shop_item')
            ? 'LEFT JOIN (SELECT shop_vnum, COUNT(*) AS item_count FROM `shop_item` GROUP BY shop_vnum) ic ON ic.shop_vnum = s.vnum'
            : '';
        $itemSelect = $this->schemaTableExists('shop_item') ? ', COALESCE(ic.item_count, 0) AS item_count' : ', 0 AS item_count';

        $rows = $this->db()->fetchAll(
            'SELECT s.vnum, s.name, s.npc_vnum' . $itemSelect . '
             FROM `shop` s
             ' . $itemJoin . '
             ' . $where . '
             ORDER BY s.vnum ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params,
        );

        return array_map(static function (array $row): array {
            return [
                'vnum' => (int) $row['vnum'],
                'name' => (string) $row['name'],
                'npc_vnum' => (int) ($row['npc_vnum'] ?? 0),
                'item_count' => (int) ($row['item_count'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForAdmin(int $vnum): ?array
    {
        if ($vnum < 1 || !$this->schemaTableExists('shop')) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT vnum, name, npc_vnum FROM `shop` WHERE vnum = ? LIMIT 1',
            [$vnum],
        );

        if ($row === null) {
            return null;
        }

        return [
            'vnum' => (int) $row['vnum'],
            'name' => (string) $row['name'],
            'npc_vnum' => (int) ($row['npc_vnum'] ?? 0),
            'items' => $this->items($vnum),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        $vnum = (int) ($input['vnum'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $npcVnum = (int) ($input['npc_vnum'] ?? 0);

        if ($vnum < 1) {
            throw new \InvalidArgumentException('admin.shops.invalid_vnum');
        }

        if ($name === '' || strlen($name) > 32) {
            throw new \InvalidArgumentException('admin.shops.invalid_name');
        }

        if ($this->findForAdmin($vnum) !== null) {
            throw new \InvalidArgumentException('admin.shops.vnum_exists');
        }

        $this->db()->execute(
            'INSERT INTO `shop` (vnum, name, npc_vnum) VALUES (?, ?, ?)',
            [$vnum, $name, $npcVnum],
        );

        $shop = $this->findForAdmin($vnum);

        if ($shop === null) {
            throw new \RuntimeException('admin.shops.create_failed');
        }

        return $shop;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $vnum, array $input): void
    {
        if ($this->findForAdmin($vnum) === null) {
            throw new \InvalidArgumentException('admin.shops.not_found');
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || strlen($name) > 32) {
            throw new \InvalidArgumentException('admin.shops.invalid_name');
        }

        $this->db()->execute(
            'UPDATE `shop` SET name = ?, npc_vnum = ? WHERE vnum = ?',
            [$name, (int) ($input['npc_vnum'] ?? 0), $vnum],
        );
    }

    public function delete(int $vnum): bool
    {
        if ($vnum < 1 || !$this->schemaTableExists('shop')) {
            return false;
        }

        $this->db()->beginTransaction();

        try {
            if ($this->schemaTableExists('shop_item')) {
                $this->db()->execute('DELETE FROM `shop_item` WHERE shop_vnum = ?', [$vnum]);
            }

            $deleted = $this->db()->execute('DELETE FROM `shop` WHERE vnum = ?', [$vnum]) > 0;
            $this->db()->commit();

            return $deleted;
        } catch (\Throwable $exception) {
            $this->db()->rollBack();

            throw $exception;
        }
    }

    public function addItem(int $shopVnum, int $itemVnum, int $count): void
    {
        if ($shopVnum < 1 || $itemVnum < 1 || $count < 1) {
            throw new \InvalidArgumentException('admin.shops.invalid_item');
        }

        if ($this->findForAdmin($shopVnum) === null) {
            throw new \InvalidArgumentException('admin.shops.not_found');
        }

        if (!$this->schemaTableExists('shop_item')) {
            throw new \RuntimeException('admin.shops.items_unavailable');
        }

        $exists = $this->db()->fetchColumn(
            'SELECT shop_vnum FROM `shop_item` WHERE shop_vnum = ? AND item_vnum = ? AND count = ? LIMIT 1',
            [$shopVnum, $itemVnum, $count],
        );

        if ($exists !== null) {
            throw new \InvalidArgumentException('admin.shops.item_exists');
        }

        $this->db()->execute(
            'INSERT INTO `shop_item` (shop_vnum, item_vnum, count) VALUES (?, ?, ?)',
            [$shopVnum, $itemVnum, $count],
        );
    }

    public function removeItem(int $shopVnum, int $itemVnum, int $count): bool
    {
        if (!$this->schemaTableExists('shop_item')) {
            return false;
        }

        return $this->db()->execute(
            'DELETE FROM `shop_item` WHERE shop_vnum = ? AND item_vnum = ? AND count = ?',
            [$shopVnum, $itemVnum, $count],
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $shopVnum): array
    {
        if (!$this->schemaTableExists('shop_item')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT shop_vnum, item_vnum, count FROM `shop_item` WHERE shop_vnum = ? ORDER BY item_vnum ASC, count ASC',
            [$shopVnum],
        );

        return array_map(static function (array $row): array {
            return [
                'shop_vnum' => (int) $row['shop_vnum'],
                'item_vnum' => (int) $row['item_vnum'],
                'count' => (int) ($row['count'] ?? 1),
            ];
        }, $rows);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function searchClause(?string $query): array
    {
        if ($query === null || $query === '') {
            return ['', []];
        }

        $like = '%' . $query . '%';

        if (ctype_digit($query)) {
            return [
                ' WHERE s.vnum = ? OR s.npc_vnum = ? OR s.name LIKE ?',
                [(int) $query, (int) $query, $like],
            ];
        }

        return [
            ' WHERE s.name LIKE ?',
            [$like],
        ];
    }
}
