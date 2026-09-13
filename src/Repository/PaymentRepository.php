<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\PaymentsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class PaymentRepository extends Repository implements ProvidesAdminGrid
{

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
        $order = GridSql::orderBy($query, PaymentsGrid::definition()->sortMap(), 'id DESC');

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

    /**
     * @return list<array<string, mixed>>
     */
    public function listExpiredPending(int $olderThanMinutes, int $limit = 100): array
    {
        $minutes = max(1, min(1440, $olderThanMinutes));
        $cutoff = date('Y-m-d H:i:s', time() - ($minutes * 60));

        return $this->db()->fetchAll(
            'SELECT id, account_id, cash_amount
             FROM cms_payments
             WHERE status = ?
               AND created_at < ?
             ORDER BY id ASC
             LIMIT ?',
            ['pending', $cutoff, max(1, min(200, $limit))],
        );
    }

    public function markPaid(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payments SET status = ?
             WHERE id = ? AND status IN (?, ?, ?)',
            ['paid', $id, 'pending', 'failed', 'expired'],
        );
    }

    public function markFailed(int $id): bool
    {
        return $this->db()->execute(
            'UPDATE cms_payments SET status = ? WHERE id = ? AND status = ?',
            ['failed', $id, 'pending'],
        ) > 0;
    }

    public function markExpired(int $id): bool
    {
        return $this->db()->execute(
            'UPDATE cms_payments SET status = ? WHERE id = ? AND status = ?',
            ['expired', $id, 'pending'],
        ) > 0;
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
     * @return list<array{currency: string, amount_cents: int, paid_count: int}>
     */
    public function paidTotalsByCurrency(string $from, string $to): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT currency,
                    COALESCE(SUM(amount_cents), 0) AS amount_cents,
                    COUNT(*) AS paid_count
             FROM cms_payments
             WHERE status = ?
               AND created_at >= ?
               AND created_at < ?
             GROUP BY currency
             ORDER BY amount_cents DESC, currency ASC',
            ['paid', $from, $to],
        );

        return array_map(static function (array $row): array {
            return [
                'currency' => strtoupper((string) ($row['currency'] ?? '')),
                'amount_cents' => (int) ($row['amount_cents'] ?? 0),
                'paid_count' => (int) ($row['paid_count'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{
     *   pending: int,
     *   failed: int,
     *   expired: int,
     *   refunded: int,
     *   refunded_by_currency: list<array{currency: string, amount_cents: int}>
     * }
     */
    public function statusCounts(string $from, string $to): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT status, COUNT(*) AS cnt
             FROM cms_payments
             WHERE created_at >= ?
               AND created_at < ?
               AND status IN (?, ?, ?, ?)
             GROUP BY status',
            [$from, $to, 'pending', 'failed', 'expired', 'refunded'],
        );

        $counts = [
            'pending' => 0,
            'failed' => 0,
            'expired' => 0,
            'refunded' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if (isset($counts[$status])) {
                $counts[$status] = (int) ($row['cnt'] ?? 0);
            }
        }

        $refundedRows = $this->db()->fetchAll(
            'SELECT currency, COALESCE(SUM(amount_cents), 0) AS amount_cents
             FROM cms_payments
             WHERE status = ?
               AND created_at >= ?
               AND created_at < ?
             GROUP BY currency
             ORDER BY amount_cents DESC, currency ASC',
            ['refunded', $from, $to],
        );

        $counts['refunded_by_currency'] = array_map(static function (array $row): array {
            return [
                'currency' => strtoupper((string) ($row['currency'] ?? '')),
                'amount_cents' => (int) ($row['amount_cents'] ?? 0),
            ];
        }, $refundedRows);

        return $counts;
    }

    /**
     * @return array{cash_amount: int, count: int}
     */
    public function cashCredited(string $from, string $to): array
    {
        $row = $this->db()->fetch(
            'SELECT COALESCE(SUM(cash_amount), 0) AS cash_amount, COUNT(*) AS cnt
             FROM cms_payments
             WHERE credited_at IS NOT NULL
               AND credited_at >= ?
               AND credited_at < ?',
            [$from, $to],
        );

        return [
            'cash_amount' => (int) ($row['cash_amount'] ?? 0),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    public function openPendingCount(): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_payments WHERE status = ?',
            ['pending'],
        );
    }

    /**
     * @return list<array{day: string, amount_cents: int}>
     */
    public function dailyPaid(string $from, string $to, string $currency): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT DATE(created_at) AS day,
                    COALESCE(SUM(amount_cents), 0) AS amount_cents
             FROM cms_payments
             WHERE status = ?
               AND currency = ?
               AND created_at >= ?
               AND created_at < ?
             GROUP BY DATE(created_at)
             ORDER BY day ASC',
            ['paid', $currency, $from, $to],
        );

        return array_map(static function (array $row): array {
            return [
                'day' => (string) ($row['day'] ?? ''),
                'amount_cents' => (int) ($row['amount_cents'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<array{day: string, cash_amount: int}>
     */
    public function dailyCashCredited(string $from, string $to): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT DATE(credited_at) AS day,
                    COALESCE(SUM(cash_amount), 0) AS cash_amount
             FROM cms_payments
             WHERE credited_at IS NOT NULL
               AND credited_at >= ?
               AND credited_at < ?
             GROUP BY DATE(credited_at)
             ORDER BY day ASC',
            [$from, $to],
        );

        return array_map(static function (array $row): array {
            return [
                'day' => (string) ($row['day'] ?? ''),
                'cash_amount' => (int) ($row['cash_amount'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::where($query, PaymentsGrid::definition()->filterSql());
    }
}
