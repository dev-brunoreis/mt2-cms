<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\Repository;

class ReferralRepository extends Repository implements ProvidesAdminGrid
{
    public function __construct(
        \Mt2Cms\Support\Database $db,
        private AccountRepository $accounts,
    ) {
        parent::__construct($db);
    }

    protected function database(): string
    {
        return 'cms';
    }

    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/game/referrals', 'admin.referrals')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'r.id',
                'referrer_login' => 'r.referrer_id',
                'referred_login' => 'r.referred_id',
                'created_at' => 'r.created_at',
                'rewarded_at' => 'r.rewarded_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.referrals.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'referrer_login', 'label' => 'admin.referrals.referrer', 'sort' => 'referrer_login', 'type' => 'text'],
                ['key' => 'referred_login', 'label' => 'admin.referrals.referred', 'sort' => 'referred_login', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'admin.referrals.created_at', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'rewarded_at', 'label' => 'admin.referrals.rewarded_at', 'sort' => 'rewarded_at', 'type' => 'date'],
            ]);
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_referrals r' . $where,
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
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'r.id DESC');

        $rows = $this->db()->fetchAll(
            'SELECT r.id, r.referrer_id, r.referred_id, r.rewarded_at, r.created_at
             FROM cms_referrals r' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        return $this->attachLogins($rows);
    }

    public function findCodeByAccountId(int $accountId): ?string
    {
        $row = $this->db()->fetch(
            'SELECT code FROM cms_referral_codes WHERE account_id = ?',
            [$accountId],
        );

        return $row !== null ? (string) ($row['code'] ?? '') : null;
    }

    public function findAccountIdByCode(string $code): ?int
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT account_id FROM cms_referral_codes WHERE code = ?',
            [$code],
        );

        return $row !== null ? (int) ($row['account_id'] ?? 0) : null;
    }

    public function assignCode(int $accountId, string $code): void
    {
        $this->db()->execute(
            'INSERT INTO cms_referral_codes (account_id, code) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE code = VALUES(code)',
            [$accountId, strtoupper($code)],
        );
    }

    public function codeExists(string $code): bool
    {
        return $this->findAccountIdByCode($code) !== null;
    }

    public function createReferral(int $referrerId, int $referredId): int
    {
        $this->db()->execute(
            'INSERT INTO cms_referrals (referrer_id, referred_id) VALUES (?, ?)',
            [$referrerId, $referredId],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function findPendingByReferredId(int $referredId): ?array
    {
        return $this->db()->fetch(
            'SELECT id, referrer_id, referred_id, rewarded_at, created_at
             FROM cms_referrals
             WHERE referred_id = ? AND rewarded_at IS NULL',
            [$referredId],
        );
    }

    public function countRewardedByReferrer(int $referrerId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_referrals WHERE referrer_id = ? AND rewarded_at IS NOT NULL',
            [$referrerId],
        );
    }

    public function markRewarded(int $referralId): bool
    {
        return $this->db()->execute(
            'UPDATE cms_referrals SET rewarded_at = NOW() WHERE id = ? AND rewarded_at IS NULL',
            [$referralId],
        ) === 1;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function attachLogins(array $rows): array
    {
        $loginCache = [];

        foreach ($rows as $index => $row) {
            $referrerId = (int) ($row['referrer_id'] ?? 0);
            $referredId = (int) ($row['referred_id'] ?? 0);
            $rows[$index]['referrer_login'] = $this->loginForAccountId($referrerId, $loginCache);
            $rows[$index]['referred_login'] = $this->loginForAccountId($referredId, $loginCache);
        }

        return $rows;
    }

    /**
     * @param array<int, string> $cache
     */
    private function loginForAccountId(int $accountId, array &$cache): string
    {
        if ($accountId < 1) {
            return '—';
        }

        if (isset($cache[$accountId])) {
            return $cache[$accountId];
        }

        $account = $this->accounts->findById($accountId);
        $login = is_array($account) ? (string) ($account['login'] ?? '') : '';

        if ($login === '') {
            $login = '#' . (string) $accountId;
        }

        $cache[$accountId] = $login;

        return $login;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $accountIds = $this->accounts->findIdsByLoginLike($query->q);

            if ($accountIds === []) {
                return [' WHERE 1 = 0', []];
            }

            $placeholders = implode(', ', array_fill(0, count($accountIds), '?'));
            $clauses[] = '(r.referrer_id IN (' . $placeholders . ') OR r.referred_id IN (' . $placeholders . '))';
            $params = array_merge($accountIds, $accountIds);
        }

        $rewarded = $query->filter('rewarded');

        if ($rewarded === '1') {
            $clauses[] = 'r.rewarded_at IS NOT NULL';
        } elseif ($rewarded === '0') {
            $clauses[] = 'r.rewarded_at IS NULL';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
