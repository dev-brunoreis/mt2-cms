<?php

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
            throw new \RuntimeException('Login already exists');
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
            throw new \RuntimeException('Failed to create account');
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
            throw new \InvalidArgumentException('Invalid login');
        }

        return $login;
    }

    private function assertEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }

        return $email;
    }

    private function assertPassword(string $password): string
    {
        $length = strlen($password);

        if ($length < 5 || $length > 16) {
            throw new \InvalidArgumentException('Invalid password');
        }

        return $password;
    }

    private function assertSocialId(string $socialid): string
    {
        if (!ctype_digit($socialid) || (int) $socialid <= 0) {
            throw new \InvalidArgumentException(
                'Social ID must be a positive number'
            );
        }

        return $socialid;
    }

    private function hashPassword(string $password): string
    {
        return '*' . strtoupper(sha1(sha1($password, true)));
    }
}
