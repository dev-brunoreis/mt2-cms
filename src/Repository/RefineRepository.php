<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class RefineRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/refine', 'admin.refine')
            ->orderBy([
                'id' => 'r.id',
                'cost' => 'r.cost',
                'prob' => 'r.prob',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.refine.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/refine/{id}'],
                ['key' => 'source_label', 'label' => 'admin.refine.source', 'type' => 'text'],
                ['key' => 'result_label', 'label' => 'admin.refine.result', 'type' => 'text'],
                ['key' => 'cost', 'label' => 'admin.refine.cost', 'sort' => 'cost', 'type' => 'number'],
                ['key' => 'prob', 'label' => 'admin.refine.prob', 'sort' => 'prob', 'type' => 'number'],
            ]);
    }

    protected function database(): string
    {
        return 'player';
    }

    public function countForAdmin(?string $query = null, ?array $usedByRefineIds = null): int
    {
        return $this->countForGrid(new GridQuery($query, 1, 20, 'id', 'asc', []), $usedByRefineIds);
    }

    /**
     * @param list<int>|null $usedByRefineIds
     */
    public function countForGrid(GridQuery $query, ?array $usedByRefineIds = null): int
    {
        if (!$this->schemaTableExists('refine_proto')) {
            return 0;
        }

        [$where, $params] = $this->gridWhere($query, $usedByRefineIds);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `refine_proto` r' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null, ?array $usedByRefineIds = null): array
    {
        return $this->listForGrid(new GridQuery($query, $page, $perPage, 'id', 'asc', []), $usedByRefineIds);
    }

    /**
     * @param list<int>|null $usedByRefineIds
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query, ?array $usedByRefineIds = null): array
    {
        if (!$this->schemaTableExists('refine_proto')) {
            return [];
        }

        [$where, $params] = $this->gridWhere($query, $usedByRefineIds);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'r.id ASC');

        $rows = $this->db()->fetchAll(
            'SELECT id, vnum0, count0, vnum1, count1, vnum2, count2, vnum3, count3, vnum4, count4,
                    src_vnum, result_vnum, cost, prob
             FROM `refine_proto` r' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForAdmin(int $id): ?array
    {
        if ($id < 1 || !$this->schemaTableExists('refine_proto')) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT id, vnum0, count0, vnum1, count1, vnum2, count2, vnum3, count3, vnum4, count4,
                    cost, src_vnum, result_vnum, prob
             FROM `refine_proto` WHERE id = ? LIMIT 1',
            [$id],
        );

        return $row === null ? null : $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): array
    {
        $data = $this->validatedInput($input);

        $this->db()->execute(
            'INSERT INTO `refine_proto`
             (vnum0, count0, vnum1, count1, vnum2, count2, vnum3, count3, vnum4, count4,
              cost, src_vnum, result_vnum, prob)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['vnum0'], $data['count0'], $data['vnum1'], $data['count1'],
                $data['vnum2'], $data['count2'], $data['vnum3'], $data['count3'],
                $data['vnum4'], $data['count4'],
                $data['cost'], $data['src_vnum'], $data['result_vnum'], $data['prob'],
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findForAdmin($id);

        if ($row === null) {
            throw new \RuntimeException('admin.refine.create_failed');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): void
    {
        if ($this->findForAdmin($id) === null) {
            throw new \InvalidArgumentException('admin.refine.not_found');
        }

        $data = $this->validatedInput($input);

        $this->db()->execute(
            'UPDATE `refine_proto`
             SET vnum0 = ?, count0 = ?, vnum1 = ?, count1 = ?, vnum2 = ?, count2 = ?,
                 vnum3 = ?, count3 = ?, vnum4 = ?, count4 = ?,
                 cost = ?, src_vnum = ?, result_vnum = ?, prob = ?
             WHERE id = ?',
            [
                $data['vnum0'], $data['count0'], $data['vnum1'], $data['count1'],
                $data['vnum2'], $data['count2'], $data['vnum3'], $data['count3'],
                $data['vnum4'], $data['count4'],
                $data['cost'], $data['src_vnum'], $data['result_vnum'], $data['prob'],
                $id,
            ],
        );
    }

    public function delete(int $id): bool
    {
        if ($id < 1 || !$this->schemaTableExists('refine_proto')) {
            return false;
        }

        return $this->db()->execute('DELETE FROM `refine_proto` WHERE id = ?', [$id]) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $materials = [];

        for ($i = 0; $i < 5; $i++) {
            $materials[] = [
                'vnum' => (int) ($row['vnum' . $i] ?? 0),
                'count' => (int) ($row['count' . $i] ?? 0),
            ];
        }

        return [
            'id' => (int) $row['id'],
            'materials' => $materials,
            'cost' => (int) ($row['cost'] ?? 0),
            'src_vnum' => (int) ($row['src_vnum'] ?? 0),
            'result_vnum' => (int) ($row['result_vnum'] ?? 0),
            'prob' => (int) ($row['prob'] ?? 100),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, int>
     */
    private function validatedInput(array $input): array
    {
        $data = [
            'cost' => max(0, (int) ($input['cost'] ?? 0)),
            'src_vnum' => max(0, (int) ($input['src_vnum'] ?? 0)),
            'result_vnum' => max(0, (int) ($input['result_vnum'] ?? 0)),
            'prob' => min(100, max(0, (int) ($input['prob'] ?? 100))),
        ];

        for ($i = 0; $i < 5; $i++) {
            $data['vnum' . $i] = max(0, (int) ($input['vnum' . $i] ?? 0));
            $data['count' . $i] = max(0, (int) ($input['count' . $i] ?? 0));
        }

        return $data;
    }

    /**
     * @param list<int>|null $usedByRefineIds
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query, ?array $usedByRefineIds = null): array
    {
        $conditions = [];
        $params = [];

        if ($query->q !== null && $query->q !== '' && ctype_digit($query->q)) {
            $like = $query->q . '%';
            $conditions[] = 'CAST(r.id AS CHAR) LIKE ?';
            $params[] = $like;

            for ($i = 0; $i < 5; $i++) {
                $conditions[] = 'CAST(r.vnum' . $i . ' AS CHAR) LIKE ?';
                $params[] = $like;
            }
        }

        if ($usedByRefineIds !== null && $usedByRefineIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($usedByRefineIds), '?'));
            $conditions[] = 'r.id IN (' . $placeholders . ')';
            array_push($params, ...$usedByRefineIds);
        }

        if ($conditions === []) {
            return ['', []];
        }

        return [' WHERE (' . implode(' OR ', $conditions) . ')', $params];
    }
}
