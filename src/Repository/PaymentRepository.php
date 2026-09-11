<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class PaymentRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/store/payments', 'admin.payments')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'id',
                'account_login' => 'account_login',
                'amount_cents' => 'amount_cents',
                'status' => 'status',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.payments.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/store/payments/{id}'],
                ['key' => 'account_login', 'label' => 'admin.payments.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'cash_amount', 'label' => 'admin.payments.cash', 'type' => 'number'],
                ['key' => 'amount_cents', 'label' => 'admin.payments.amount', 'sort' => 'amount_cents', 'type' => 'number'],
                ['key' => 'provider', 'label' => 'admin.payments.provider', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.payments.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'pending' => ['class' => 'admin-badge-warn', 'label' => 'admin.payments.status_pending'],
                    'paid' => ['class' => 'admin-badge-ok', 'label' => 'admin.payments.status_paid'],
                    'failed' => ['class' => 'admin-badge-danger', 'label' => 'admin.payments.status_failed'],
                    'refunded' => ['class' => 'admin-badge-muted', 'label' => 'admin.payments.status_refunded'],
                ]],
                ['key' => 'created_at', 'label' => 'admin.payments.created', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.payments.status', 'type' => 'select', 'options' => [
                    'pending' => 'admin.payments.status_pending',
                    'paid' => 'admin.payments.status_paid',
                    'failed' => 'admin.payments.status_failed',
                    'refunded' => 'admin.payments.status_refunded',
                ]],
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
            'SELECT COUNT(*) FROM cms_payments' . $where,
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
            'SELECT id, account_id, account_login, package_id, provider, provider_ref,
                    amount_cents, currency, cash_amount, status, credited_at, created_at
             FROM cms_payments' . $where . $order . ' LIMIT ? OFFSET ?',
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByAccountId(int $accountId, int $limit = 50): array
    {
        return $this->db()->fetchAll(
            'SELECT id, cash_amount, amount_cents, currency, status, credited_at, created_at
             FROM cms_payments
             WHERE account_id = ?
             ORDER BY id DESC
             LIMIT ?',
            [$accountId, max(1, min(100, $limit))],
        );
    }

    public function findByProviderRef(string $provider, string $ref): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, package_id, provider, provider_ref,
                    amount_cents, currency, cash_amount, status, credited_at, created_at
             FROM cms_payments
             WHERE provider = ? AND provider_ref = ?
             LIMIT 1',
            [$provider, $ref],
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, package_id, provider, provider_ref,
                    amount_cents, currency, cash_amount, status, credited_at, created_at
             FROM cms_payments WHERE id = ?',
            [$id],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPending(array $data): array
    {
        $this->db()->execute(
            'INSERT INTO cms_payments
             (account_id, account_login, package_id, provider, provider_ref, amount_cents, currency, cash_amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['account_id'],
                $data['account_login'],
                $data['package_id'],
                $data['provider'],
                $data['provider_ref'],
                $data['amount_cents'],
                $data['currency'],
                $data['cash_amount'],
                'pending',
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('payments.create_failed');
        }

        return $row;
    }

    public function markPaid(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payments SET status = ? WHERE id = ? AND status = ?',
            ['paid', $id, 'pending'],
        );
    }

    public function markFailed(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payments SET status = ? WHERE id = ? AND status = ?',
            ['failed', $id, 'pending'],
        );
    }

    public function markCredited(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payments SET credited_at = NOW() WHERE id = ? AND credited_at IS NULL',
            [$id],
        );
    }

    public function updateProviderRef(int $id, string $providerRef): void
    {
        $this->db()->execute(
            'UPDATE cms_payments SET provider_ref = ? WHERE id = ?',
            [$providerRef, $id],
        );
    }

    public function findByIdForAccount(int $id, int $accountId): ?array
    {
        return $this->db()->fetch(
            'SELECT id, provider, provider_ref, status, credited_at
             FROM cms_payments WHERE id = ? AND account_id = ?',
            [$id, $accountId],
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];
        $status = $query->filter('status');

        if ($status !== '' && in_array($status, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        if ($query->q !== null && $query->q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
            $clauses[] = 'account_login LIKE ?';
            $params[] = '%' . $escaped . '%';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
