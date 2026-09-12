<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\DashboardPlayersGrid;
use Mt2Cms\Admin\Grid\Definitions\PlayersGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class PlayerRepository extends Repository implements ProvidesAdminGrid
{


    private const PUBLIC_COLUMNS = 'id, name, job, skill_group, level, exp, playtime, last_play, map_index';
    private const DETAIL_COLUMNS = 'id, account_id, name, job, skill_group, level, exp, gold, playtime, map_index, last_play';
    private const ADMIN_COLUMNS = 'p.id, p.account_id, p.name, p.job, p.skill_group, p.level, p.exp, p.gold, p.playtime, p.map_index, p.last_play';

    private ?bool $positionColumnsAvailable = null;

    protected function database(): string
    {
        return 'player';
    }

    public function hasPositionColumns(): bool
    {
        if ($this->positionColumnsAvailable !== null) {
            return $this->positionColumnsAvailable;
        }

        if (!$this->schemaTableExists('player')) {
            $this->positionColumnsAvailable = false;

            return false;
        }

        $rows = $this->db()->fetchAll(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?)',
            [$this->database(), 'player', 'x', 'y'],
        );

        $found = [];

        foreach ($rows as $row) {
            $found[(string) ($row['COLUMN_NAME'] ?? '')] = true;
        }

        $this->positionColumnsAvailable = isset($found['x'], $found['y']);

        return $this->positionColumnsAvailable;
    }

    public function teleportTo(int $playerId, int $accountId, int $mapIndex, int $x, int $y): bool
    {
        if (!$this->hasPositionColumns()) {
            return false;
        }

        return $this->db()->execute(
            'UPDATE `player` SET map_index = ?, x = ?, y = ? WHERE id = ? AND account_id = ?',
            [$mapIndex, $x, $y, $playerId, $accountId],
        ) === 1;
    }

    public function maxLevelByAccountId(int $accountId): int
    {
        $level = $this->db()->fetchColumn(
            'SELECT MAX(level) FROM `player` WHERE account_id = ?',
            [$accountId],
        );

        return (int) ($level ?? 0);
    }

    public function findById(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT ' . self::DETAIL_COLUMNS . '
                 FROM `player` WHERE id = ?',
                [$id],
            ),
        );
    }

    public function findByName(string $name): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT ' . self::DETAIL_COLUMNS . '
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
                'SELECT ' . self::DETAIL_COLUMNS . '
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

    public function countPlaytimeRanking(?string $q = null): int
    {
        return $this->countRanking($q);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPlaytimeRanking(int $page, int $perPage, ?string $q = null): array
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
                 ORDER BY playtime DESC, level DESC, id ASC
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
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
     * @return list<array<string, mixed>>
     */
    public function listActiveSinceMinutes(int $minutes, int $page, int $perPage): array
    {
        return $this->listActiveForGrid(new GridQuery(null, $page, $perPage, 'last_play', 'desc', []), $minutes);
    }

    public function countActiveForGrid(GridQuery $query): int
    {
        $minutes = self::rangeMinutes($query->filter('range', '5m'));
        [$where, $params] = $this->activeWhere($minutes);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `player` p' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForGrid(GridQuery $query, ?int $minutesOverride = null): array
    {
        $minutes = $minutesOverride ?? self::rangeMinutes($query->filter('range', '5m'));
        [$where, $params] = $this->activeWhere($minutes);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, DashboardPlayersGrid::definition()->sortMap(), 'p.last_play DESC, p.id DESC');

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT ' . self::ADMIN_COLUMNS . ',
                        a.login AS account_login
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id' . $where . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    public static function rangeMinutes(string $range): int
    {
        return match ($range) {
            '1h' => 60,
            '12h' => 720,
            '24h' => 1440,
            '7d' => 10080,
            default => 5,
        };
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

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
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, PlayersGrid::definition()->sortMap(), 'p.id DESC');

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT ' . self::ADMIN_COLUMNS . ',
                        a.login AS account_login
                 FROM `player` p
                 LEFT JOIN `account`.`account` a ON a.id = p.account_id' . $where . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    public function findForAdmin(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT ' . self::ADMIN_COLUMNS . ',
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
    private function activeWhere(int $minutes): array
    {
        return [
            ' WHERE p.last_play >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [max(1, $minutes)],
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $q): array
    {
        return $this->gridWhere(new GridQuery($q, 1, 20, 'id', 'desc', []));
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        return GridSql::where($query, PlayersGrid::definition()->filterSql());
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
