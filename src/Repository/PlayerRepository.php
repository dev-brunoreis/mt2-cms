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

    public function findEmpireByAccountId(int $accountId): int
    {
        $map = $this->mapEmpiresByAccountIds([$accountId]);

        return $map[$accountId] ?? 0;
    }

    /**
     * @param list<int> $accountIds
     * @return array<int, int>
     */
    public function mapEmpiresByAccountIds(array $accountIds): array
    {
        $ids = [];

        foreach ($accountIds as $accountId) {
            $id = (int) $accountId;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $ids = array_values($ids);

        if ($ids === [] || !$this->schemaTableExists('player_index')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db()->fetchAll(
            'SELECT id, empire FROM `player_index` WHERE id IN (' . $placeholders . ')',
            $ids,
        );

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['id']] = (int) ($row['empire'] ?? 0);
        }

        return $map;
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

    public function countActiveSinceMinutes(int $minutes): int
    {
        $minutes = max(1, $minutes);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `player` WHERE last_play >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$minutes],
        );
    }

    public function countAccountsActiveSinceMinutes(int $minutes): int
    {
        $minutes = max(1, $minutes);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(DISTINCT account_id) FROM `player` WHERE last_play >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$minutes],
        );
    }

    /**
     * @return array<int, int>
     */
    public function countByMap(): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT CASE WHEN map_index >= 10000 THEN FLOOR(map_index / 10000) ELSE map_index END AS map_id,
                    COUNT(*) AS total
             FROM `player`
             GROUP BY map_id',
        );
        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['map_id']] = (int) $row['total'];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOnMap(int $mapIndex): array
    {
        $mapIndex = max(0, $mapIndex);
        $hasEmpire = $this->schemaTableExists('player_index');
        $empireSelect = $hasEmpire ? ', pi.empire' : '';
        $empireJoin = $hasEmpire ? ' LEFT JOIN `player_index` pi ON pi.id = p.account_id' : '';

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT p.id, p.account_id, p.name, p.job, p.level, p.map_index, p.x, p.y, p.last_play,
                        a.login AS account_login' . $empireSelect . '
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id' . $empireJoin . '
                 WHERE p.map_index = ? OR FLOOR(p.map_index / 10000) = ?
                 ORDER BY p.name ASC, p.id ASC',
                [$mapIndex, $mapIndex],
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveSinceMinutes(int $minutes, int $page, int $perPage): array
    {
        $minutes = max(1, $minutes);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT p.id, p.account_id, p.name, p.job, p.level, p.exp, p.gold, p.playtime, p.map_index, p.last_play,
                        a.login AS account_login
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id
                 WHERE p.last_play >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 ORDER BY p.last_play DESC, p.id DESC
                 LIMIT ? OFFSET ?',
                [$minutes, $perPage, $offset],
            ),
        );
    }

    public function countForAdmin(?string $q = null): int
    {
        [$where, $params] = $this->adminWhere($q);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*)
             FROM `player` p
             LEFT JOIN `account`.`account` a ON a.id = p.account_id' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $q = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->adminWhere($q);
        $params[] = $perPage;
        $params[] = $offset;

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT p.id, p.account_id, p.name, p.job, p.level, p.exp, p.gold, p.playtime, p.map_index, p.last_play,
                        a.login AS account_login
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id' . $where . '
                 ORDER BY p.id DESC
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    public function findForAdmin(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT p.id, p.account_id, p.name, p.job, p.level, p.exp, p.gold, p.playtime, p.map_index, p.last_play,
                        a.login AS account_login
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id
                 WHERE p.id = ?',
                [$id],
            ),
        );
    }

    /**
     * @return array{
     *   is_married: bool,
     *   love_point: int,
     *   married_at: string|null,
     *   partner_id: int,
     *   partner_name: string|null
     * }|null
     */
    public function findMarriageForPlayer(int $playerId): ?array
    {
        if ($playerId < 1 || !$this->schemaTableExists('marriage')) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT m.is_married, m.pid1, m.pid2, m.love_point, m.time,
                    p1.name AS name1, p2.name AS name2
             FROM `marriage` m
             LEFT JOIN `player` p1 ON p1.id = m.pid1
             LEFT JOIN `player` p2 ON p2.id = m.pid2
             WHERE m.pid1 = ? OR m.pid2 = ?
             LIMIT 1',
            [$playerId, $playerId],
        );

        if ($row === null) {
            return null;
        }

        $pid1 = (int) $row['pid1'];
        $isFirst = $pid1 === $playerId;
        $time = (int) ($row['time'] ?? 0);

        return [
            'is_married' => (int) ($row['is_married'] ?? 0) === 1,
            'love_point' => (int) ($row['love_point'] ?? 0),
            'married_at' => $time > 0 ? gmdate('Y-m-d H:i:s', $time) : null,
            'partner_id' => $isFirst ? (int) $row['pid2'] : $pid1,
            'partner_name' => $isFirst
                ? ($row['name2'] !== null ? (string) $row['name2'] : null)
                : ($row['name1'] !== null ? (string) $row['name1'] : null),
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $q): array
    {
        if ($q === null || $q === '') {
            return ['', []];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = '%' . $escaped . '%';

        return [
            ' WHERE p.name LIKE ? OR a.login LIKE ?',
            [$like, $like],
        ];
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
