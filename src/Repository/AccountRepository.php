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
}
