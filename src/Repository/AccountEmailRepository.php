<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class AccountEmailRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function findByAccountId(int $accountId): ?array
    {
        if ($accountId < 1) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT account_id, email, verified_at, updated_at
             FROM cms_account_email
             WHERE account_id = ?
             LIMIT 1',
            [$accountId],
        );
    }

    public function isVerified(int $accountId): bool
    {
        $row = $this->findByAccountId($accountId);

        return $row !== null && $row['verified_at'] !== null;
    }

    public function upsert(int $accountId, string $email, ?bool $verified = null): void
    {
        if ($accountId < 1) {
            throw new \InvalidArgumentException('account.invalid');
        }

        $email = trim($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('auth.invalid_email');
        }

        $existing = $this->findByAccountId($accountId);

        if ($existing === null) {
            $verifiedAt = $verified === true ? date('Y-m-d H:i:s') : null;
            $this->db()->execute(
                'INSERT INTO cms_account_email (account_id, email, verified_at)
                 VALUES (?, ?, ?)',
                [$accountId, $email, $verifiedAt],
            );

            return;
        }

        if ($verified === true) {
            $this->db()->execute(
                'UPDATE cms_account_email SET email = ?, verified_at = NOW() WHERE account_id = ?',
                [$email, $accountId],
            );

            return;
        }

        if ($verified === false) {
            $this->db()->execute(
                'UPDATE cms_account_email SET email = ?, verified_at = NULL WHERE account_id = ?',
                [$email, $accountId],
            );

            return;
        }

        $this->db()->execute(
            'UPDATE cms_account_email SET email = ? WHERE account_id = ?',
            [$email, $accountId],
        );
    }

    public function markVerified(int $accountId): void
    {
        $this->db()->execute(
            'UPDATE cms_account_email SET verified_at = NOW() WHERE account_id = ?',
            [$accountId],
        );
    }
}
