<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class EmailTokenRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @return array{plain: string, hash: string}
     */
    public function create(int $accountId, string $type, string $email, int $ttlSeconds): array
    {
        if ($accountId < 1) {
            throw new \InvalidArgumentException('account.invalid');
        }

        if (!in_array($type, ['verify', 'reset', 'change_email'], true)) {
            throw new \InvalidArgumentException('email.invalid_token_type');
        }

        $plain = bin2hex(random_bytes(32));
        $hash = self::hashToken($plain);
        $expires = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));

        $this->db()->execute(
            'INSERT INTO cms_email_tokens (account_id, token_hash, type, email, expires_at)
             VALUES (?, ?, ?, ?, ?)',
            [$accountId, $hash, $type, $email !== '' ? $email : null, $expires],
        );

        return ['plain' => $plain, 'hash' => $hash];
    }

    public function consume(string $plain, string $type): ?array
    {
        $hash = self::hashToken($plain);

        $row = $this->db()->fetch(
            'SELECT id, account_id, token_hash, type, email, expires_at, used_at
             FROM cms_email_tokens
             WHERE token_hash = ? AND type = ?
             LIMIT 1',
            [$hash, $type],
        );

        if ($row === null) {
            return null;
        }

        if ($row['used_at'] !== null) {
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        if (!hash_equals((string) $row['token_hash'], $hash)) {
            return null;
        }

        $this->db()->execute(
            'UPDATE cms_email_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL',
            [(int) $row['id']],
        );

        return $row;
    }

    public static function hashToken(string $plain): string
    {
        $key = (string) (\Mt2Cms\Support\Env::getInstance()->get('APP_KEY') ?? '');

        return hash('sha256', $plain . $key);
    }
}
