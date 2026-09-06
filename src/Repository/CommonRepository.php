<?php

namespace Mt2Cms\Repository;

class CommonRepository extends Repository
{
    protected function database(): string
    {
        return 'common';
    }

    public function gmList(): array
    {
        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT mID, mAccount, mName, mAuthority FROM `gmlist`',
            ),
        );
    }

    public function findGmByAccount(string $account): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT mID, mAccount, mName, mAuthority FROM `gmlist` WHERE mAccount = ?',
                [$account],
            ),
        );
    }

    public function locales(): array
    {
        return $this->revealAll($this->db()->fetchAll('SELECT * FROM `locale`'));
    }
}
