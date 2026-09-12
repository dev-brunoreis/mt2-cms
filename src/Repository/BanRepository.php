<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\BansGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;
use Mt2Cms\Repository\Repository;

class BanRepository extends Repository implements ProvidesAdminGrid
{

    protected function database(): string
    {
        return 'cms';
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_bans' . $where,
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
        $order = GridSql::orderBy($query, BansGrid::definition()->sortMap(), 'id DESC');

        return $this->db()->fetchAll(
            'SELECT id, account_id, account_login, reason, expires_at, created_by_admin_id, created_at, lifted_at
             FROM cms_bans' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    public function create(int $accountId, string $login, string $reason, ?string $expiresAt, ?int $adminId): int
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('admin.bans.reason_required');
        }

        $this->db()->execute(
            'INSERT INTO cms_bans (account_id, account_login, reason, expires_at, created_by_admin_id)
             VALUES (?, ?, ?, ?, ?)',
            [$accountId, $login, mb_substr($reason, 0, 512), $expiresAt, $adminId],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, reason, expires_at, created_at, lifted_at
             FROM cms_bans WHERE id = ?',
            [$id],
        );
    }

    public function findActiveByAccountId(int $accountId): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, reason, expires_at, created_at, lifted_at
             FROM cms_bans
             WHERE account_id = ? AND lifted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [$accountId],
        );
    }

    public function lift(int $banId): bool
    {
        return $this->db()->execute(
            'UPDATE cms_bans SET lifted_at = NOW() WHERE id = ? AND lifted_at IS NULL',
            [$banId],
        ) > 0;
    }

    public function liftByAccountId(int $accountId): void
    {
        $this->db()->execute(
            'UPDATE cms_bans SET lifted_at = NOW() WHERE account_id = ? AND lifted_at IS NULL',
            [$accountId],
        );
    }

    public function expireDue(): int
    {
        return $this->db()->execute(
            'UPDATE cms_bans SET lifted_at = NOW()
             WHERE lifted_at IS NULL AND expires_at IS NOT NULL AND expires_at < NOW()',
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::where($query, BansGrid::definition()->filterSql());
    }
}
