<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class AccountRepository extends Repository
{
    protected function database(): string
    {
        return 'account';
    }

    public function findById(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account` WHERE id = ?',
                [$id],
            ),
        );
    }

    public function findByLogin(string $login): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account` WHERE login = ?',
                [$login],
            ),
        );
    }

    /**
     * Verify credentials. Returns a public account row or null.
     * Password is never returned.
     */
    public function authenticate(string $login, string $password): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, login, password, status, empire, cash, mileage, create_time, last_play
             FROM `account` WHERE login = ?',
            [$login],
        );

        if ($row === null) {
            return null;
        }

        if (($row['status'] ?? '') === 'BLOCK') {
            return null;
        }

        $hash = (string) ($row['password'] ?? '');

        if ($hash === '' || !hash_equals($hash, $this->hashPassword($password))) {
            return null;
        }

        return $this->reveal($row);
    }

    public function all(): array
    {
        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account`',
            ),
        );
    }

    public function create(string $login, string $email, string $password, string $socialId): array
    {
        $login = $this->assertLogin($login);
        $email = $this->assertEmail($email);
        $password = $this->assertPassword($password);
        $socialId = $this->assertSocialId($socialId);

        if ($this->findByLogin($login)) {
            throw new \RuntimeException('error.login_exists');
        }

        $now = date('Y-m-d H:i:s');
        $db = $this->db();

        $db->execute(
            'INSERT INTO `account` (`login`, `email`, `password`, `social_id`, `create_time`)
             VALUES (?, ?, ?, ?, ?)',
            [$login, $email, $this->hashPassword($password), $socialId, $now],
        );

        $id = (int) $db->lastInsertId();
        $account = $id > 0 ? $this->findById($id) : $this->findByLogin($login);

        if ($account === null) {
            throw new \RuntimeException('error.account_create_failed');
        }

        return $account;
    }

    public function block(int $id): array
    {
        return $this->setStatus($id, 'BLOCK');
    }

    public function unblock(int $id): array
    {
        return $this->setStatus($id, 'OK');
    }

    public function delete(int $id): bool
    {
        $this->assertId($id);

        return $this->db()->execute('DELETE FROM `account` WHERE id = ?', [$id]) > 0;
    }

    private function setStatus(int $id, string $status): array
    {
        $this->assertId($id);

        if (!in_array($status, ['OK', 'BLOCK'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        if ($this->findById($id) === null) {
            throw new \RuntimeException('Account not found');
        }

        $this->db()->execute(
            'UPDATE `account` SET status = ? WHERE id = ?',
            [$status, $id],
        );

        return $this->findById($id);
    }

    private function assertId(int $id): void
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Invalid account id');
        }
    }

    private function assertLogin(string $login): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{2,30}$/', $login)) {
            throw new \InvalidArgumentException('error.invalid_login');
        }

        return $login;
    }

    private function assertEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('error.invalid_email');
        }

        return $email;
    }

    private function assertPassword(string $password): string
    {
        $length = strlen($password);

        if ($length < 5 || $length > 16) {
            throw new \InvalidArgumentException('error.invalid_password');
        }

        return $password;
    }

    private function assertSocialId(string $socialid): string
    {
        if (!ctype_digit($socialid) || (int) $socialid <= 0) {
            throw new \InvalidArgumentException('error.invalid_social_id');
        }

        if (strlen($socialid) < 7) {
            throw new \InvalidArgumentException('error.social_id_min_length');
        }

        return $socialid;
    }

    private function hashPassword(string $password): string
    {
        return '*' . strtoupper(sha1(sha1($password, true)));
    }
}
