<?php

declare(strict_types=1);

namespace Mt2Cms\Ban;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;
use Mt2Cms\Repository\Repository;

class BanRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/game/bans', 'admin.bans')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'created_at' => 'created_at',
                'expires_at' => 'expires_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.bans.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'account_login', 'label' => 'admin.bans.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'reason', 'label' => 'admin.bans.reason', 'type' => 'text'],
                ['key' => 'expires_at', 'label' => 'admin.bans.expires', 'sort' => 'expires_at', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'admin.bans.created', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'lifted_at', 'label' => 'admin.bans.lifted', 'type' => 'date'],
            ])
            ->massActions('/admin/game/bans/mass', [
                ['id' => 'lift', 'label' => 'admin.bans.lift', 'confirm' => 'admin.bans.confirm_mass_lift'],
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
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'id DESC');

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
        $clauses = [];
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
            $clauses[] = '(account_login LIKE ? OR reason LIKE ?)';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }

        $active = $query->filter('active');

        if ($active === '1') {
            $clauses[] = 'lifted_at IS NULL';
        } elseif ($active === '0') {
            $clauses[] = 'lifted_at IS NOT NULL';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
