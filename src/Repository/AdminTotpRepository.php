<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Auth\Totp;
use Mt2Cms\Model\Database;

class AdminTotpRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /** @return list<string> */
    protected function hiddenColumns(): array
    {
        return ['password', 'totp_secret'];
    }

    public function isEnabled(int $adminId): bool
    {
        if ($adminId < 1) {
            return false;
        }

        $value = $this->db()->fetchColumn(
            'SELECT totp_enabled FROM admins WHERE id = ? LIMIT 1',
            [$adminId],
        );

        return (int) $value === 1;
    }

    public function secretFor(int $adminId): ?string
    {
        if ($adminId < 1) {
            return null;
        }

        $secret = $this->db()->fetchColumn(
            'SELECT totp_secret FROM admins WHERE id = ? LIMIT 1',
            [$adminId],
        );

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * @return list<string> Plain recovery codes (shown once).
     */
    public function enable(int $adminId, string $secret): array
    {
        if ($adminId < 1 || $secret === '') {
            throw new \InvalidArgumentException('admin.2fa.invalid_secret');
        }

        $codes = Totp::generateRecoveryCodes();
        $this->db()->beginTransaction();

        try {
            $this->db()->execute(
                'UPDATE admins SET totp_secret = ?, totp_enabled = 1 WHERE id = ?',
                [$secret, $adminId],
            );
            $this->db()->execute('DELETE FROM admin_totp_recovery_codes WHERE admin_id = ?', [$adminId]);

            foreach ($codes as $code) {
                $this->db()->execute(
                    'INSERT INTO admin_totp_recovery_codes (admin_id, code_hash) VALUES (?, ?)',
                    [$adminId, password_hash($code, PASSWORD_DEFAULT)],
                );
            }

            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            throw $e;
        }

        return $codes;
    }

    public function disable(int $adminId): void
    {
        if ($adminId < 1) {
            return;
        }

        $this->db()->beginTransaction();

        try {
            $this->db()->execute(
                'UPDATE admins SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?',
                [$adminId],
            );
            $this->db()->execute('DELETE FROM admin_totp_recovery_codes WHERE admin_id = ?', [$adminId]);
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();

            throw $e;
        }
    }

    public function verifyRecoveryCode(int $adminId, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($adminId < 1 || $code === '') {
            return false;
        }

        $rows = $this->db()->fetchAll(
            'SELECT id, code_hash FROM admin_totp_recovery_codes
             WHERE admin_id = ? AND used_at IS NULL',
            [$adminId],
        );

        foreach ($rows as $row) {
            if (!password_verify($code, (string) $row['code_hash'])) {
                continue;
            }

            $this->db()->execute(
                'UPDATE admin_totp_recovery_codes SET used_at = NOW() WHERE id = ?',
                [(int) $row['id']],
            );

            return true;
        }

        return false;
    }
}
