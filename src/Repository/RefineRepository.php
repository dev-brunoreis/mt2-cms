<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class RefineRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
    }

    public function countForAdmin(?string $query = null): int
    {
        if (!$this->schemaTableExists('refine_proto')) {
            return 0;
        }

        [$where, $params] = $this->searchClause($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `refine_proto` r' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null): array
    {
        if (!$this->schemaTableExists('refine_proto')) {
            return [];
        }

        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->searchClause($query);

        $rows = $this->db()->fetchAll(
            'SELECT id, src_vnum, result_vnum, cost, prob
             FROM `refine_proto` r
             ' . $where . '
             ORDER BY id ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
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
     * @return array{0: string, 1: list<mixed>}
     */
    private function searchClause(?string $query): array
    {
        if ($query === null || $query === '') {
            return ['', []];
        }

        if (ctype_digit($query)) {
            $id = (int) $query;

            return [
                ' WHERE r.id = ? OR r.src_vnum = ? OR r.result_vnum = ?',
                [$id, $id, $id],
            ];
        }

        return ['', []];
    }
}
