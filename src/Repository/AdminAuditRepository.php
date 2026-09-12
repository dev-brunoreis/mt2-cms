<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\AdminAuditGrid;
use Mt2Cms\Admin\AdminAuditMeta;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class AdminAuditRepository extends Repository implements ProvidesAdminGrid
{
    protected function database(): string
    {
        return 'cms';
    }


    /**
     * @param array<string, mixed>|null $meta
     */
    public function insert(
        int $adminId,
        string $login,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $meta,
        string $ip,
    ): void {
        $metaJson = null;

        if ($meta !== null && $meta !== []) {
            $metaJson = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $this->db()->execute(
            'INSERT INTO admin_audit_log
                (admin_id, login, action, target_type, target_id, meta, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$adminId, $login, $action, $targetType, $targetId, $metaJson, $ip],
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM admin_audit_log' . $where,
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
        $order = GridSql::orderBy($query, AdminAuditGrid::definition()->sortMap(), 'id DESC');

        $rows = $this->db()->fetchAll(
            'SELECT id, admin_id, login, action, target_type, target_id, meta, ip, created_at
             FROM admin_audit_log' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        foreach ($rows as &$row) {
            $columns = AdminAuditMeta::displayColumns($row['meta'] ?? null);
            $row['before'] = $columns['before'];
            $row['after'] = $columns['after'];
            unset($row['meta']);
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $where = ' WHERE 1=1';
        $params = [];

        foreach ($query->filters as $key => $value) {
            if ($value === '') {
                continue;
            }

            if ($key === 'login') {
                $where .= ' AND login LIKE ?';
                $params[] = '%' . $value . '%';
            } elseif ($key === 'action') {
                $where .= ' AND action LIKE ?';
                $params[] = '%' . $value . '%';
            } elseif ($key === 'target_type') {
                $where .= ' AND target_type LIKE ?';
                $params[] = '%' . $value . '%';
            }
        }

        return [$where, $params];
    }
}
