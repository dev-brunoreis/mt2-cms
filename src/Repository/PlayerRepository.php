<?php

namespace Mt2Cms\Repository;

class PlayerRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
    }

    public function findById(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, account_id, name, job, level, exp, gold, playtime, map_index, last_play
                 FROM `player` WHERE id = ?',
                [$id],
            ),
        );
    }

    public function findByName(string $name): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, account_id, name, job, level, exp, gold, playtime, map_index, last_play
                 FROM `player` WHERE name = ?',
                [$name],
            ),
        );
    }

    public function findByAccountId(int $accountId): array
    {
        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT id, account_id, name, job, level, exp, gold, playtime, map_index, last_play
                 FROM `player` WHERE account_id = ?',
                [$accountId],
            ),
        );
    }
}
