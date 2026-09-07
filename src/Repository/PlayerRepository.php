<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class PlayerRepository extends Repository
{
    private const PUBLIC_COLUMNS = 'id, name, job, level, exp, playtime, last_play, map_index';

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

    /**
     * Public player profile without account_id or gold.
     */
    public function findPublicByName(string $name): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT ' . self::PUBLIC_COLUMNS . ' FROM `player` WHERE name = ?',
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

    public function countRanking(?string $q = null): int
    {
        [$where, $params] = $this->rankingWhere($q);

        $count = $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `player`' . $where,
            $params,
        );

        return (int) $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRanking(int $page, int $perPage, ?string $q = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->rankingWhere($q);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT ' . self::PUBLIC_COLUMNS . '
                 FROM `player`' . $where . '
                 ORDER BY level DESC, exp DESC, id ASC
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function rankingWhere(?string $q): array
    {
        if ($q === null || $q === '') {
            return ['', []];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);

        return [' WHERE name LIKE ?', ['%' . $escaped . '%']];
    }
}
