<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class AdminRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /** @return list<string> */
    protected function hiddenColumns(): array
    {
        return ['password'];
    }

    public function create(string $login, string $password): int
    {
        $login = trim($login);

        if ($login === '' || strlen($login) > 64) {
            throw new \InvalidArgumentException('admin.invalid_login');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('admin.invalid_password');
        }

        if ($this->findByLogin($login) !== null) {
            throw new \RuntimeException('admin.login_exists');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->db()->execute(
            'INSERT INTO admins (login, password, role) VALUES (?, ?, ?)',
            [$login, $hash, 'super'],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function findByLogin(string $login): ?array
    {
        return $this->reveal($this->db()->fetch(
            'SELECT id, login, password, created_at FROM admins WHERE login = ? LIMIT 1',
            [$login],
        ));
    }

    public function findById(int $id): ?array
    {
        return $this->reveal($this->db()->fetch(
            'SELECT id, login, role, created_at FROM admins WHERE id = ? LIMIT 1',
            [$id],
        ));
    }

    public function authenticate(string $login, string $password): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, login, password FROM admins WHERE login = ? LIMIT 1',
            [trim($login)],
        );

        if ($row === null) {
            return null;
        }

        if (!password_verify($password, (string) $row['password'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'login' => (string) $row['login'],
        ];
    }

    public function count(): int
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM admins');
    }
}
