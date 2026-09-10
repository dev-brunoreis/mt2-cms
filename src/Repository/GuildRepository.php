<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class GuildRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/guilds', 'admin.guilds')
            ->orderBy([
                'id' => 'g.id',
                'name' => 'g.name',
                'level' => 'g.level',
                'member_count' => 'member_count',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.guilds.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'name', 'label' => 'admin.guilds.name', 'sort' => 'name', 'type' => 'link', 'href' => '/admin/guilds/{id}'],
                ['key' => 'level', 'label' => 'admin.guilds.level', 'sort' => 'level', 'type' => 'number'],
                ['key' => 'member_count', 'label' => 'admin.guilds.members', 'sort' => 'member_count', 'type' => 'number'],
                ['key' => 'master', 'label' => 'admin.guilds.master', 'type' => 'text'],
            ]);
    }

    protected function database(): string
    {
        return 'player';
    }

    public function countForAdmin(?string $query = null): int
    {
        return $this->countForGrid(new GridQuery($query, 1, 20, 'id', 'asc', []));
    }

    public function countForGrid(GridQuery $query): int
    {
        if (!$this->schemaTableExists('guild')) {
            return 0;
        }

        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*)
             FROM `guild` g
             LEFT JOIN `player` p ON p.id = g.master' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(int $page, int $perPage, ?string $query = null): array
    {
        return $this->listForGrid(new GridQuery($query, $page, $perPage, 'id', 'asc', []));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        if (!$this->schemaTableExists('guild')) {
            return [];
        }

        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $memberJoin = $this->schemaTableExists('guild_member')
            ? 'LEFT JOIN (SELECT guild_id, COUNT(*) AS member_count FROM `guild_member` GROUP BY guild_id) mc ON mc.guild_id = g.id'
            : '';
        $memberSelect = $this->schemaTableExists('guild_member') ? ', COALESCE(mc.member_count, 0) AS member_count' : ', 0 AS member_count';
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'g.id ASC');

        $rows = $this->revealAll(
            $this->db()->fetchAll(
                'SELECT g.id, g.name, g.master, g.level, g.exp, g.gold, g.ladder_point,
                        g.win, g.draw, g.loss, p.name AS master_name' . $memberSelect . '
                 FROM `guild` g
                 LEFT JOIN `player` p ON p.id = g.master
                 ' . $memberJoin . '
                 ' . $where . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'master_id' => (int) ($row['master'] ?? 0),
                'master_name' => $row['master_name'] !== null ? (string) $row['master_name'] : null,
                'level' => (int) ($row['level'] ?? 0),
                'exp' => (int) ($row['exp'] ?? 0),
                'gold' => (int) ($row['gold'] ?? 0),
                'ladder_point' => (int) ($row['ladder_point'] ?? 0),
                'win' => (int) ($row['win'] ?? 0),
                'draw' => (int) ($row['draw'] ?? 0),
                'loss' => (int) ($row['loss'] ?? 0),
                'member_count' => (int) ($row['member_count'] ?? 0),
                'master' => $row['master_name'] !== null ? (string) $row['master_name'] : null,
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForAdmin(int $guildId): ?array
    {
        if ($guildId < 1 || !$this->schemaTableExists('guild')) {
            return null;
        }

        $row = $this->reveal(
            $this->db()->fetch(
                'SELECT g.id, g.name, g.master, g.level, g.exp, g.gold, g.ladder_point,
                        g.win, g.draw, g.loss, g.sp, g.skill_point,
                        p.name AS master_name
                 FROM `guild` g
                 LEFT JOIN `player` p ON p.id = g.master
                 WHERE g.id = ?
                 LIMIT 1',
                [$guildId],
            ),
        );

        if ($row === null) {
            return null;
        }

        $masterId = (int) ($row['master'] ?? 0);

        return [
            'id' => $guildId,
            'name' => (string) $row['name'],
            'master_id' => $masterId,
            'master_name' => $row['master_name'] !== null ? (string) $row['master_name'] : null,
            'level' => (int) ($row['level'] ?? 0),
            'exp' => (int) ($row['exp'] ?? 0),
            'gold' => (int) ($row['gold'] ?? 0),
            'ladder_point' => (int) ($row['ladder_point'] ?? 0),
            'win' => (int) ($row['win'] ?? 0),
            'draw' => (int) ($row['draw'] ?? 0),
            'loss' => (int) ($row['loss'] ?? 0),
            'sp' => (int) ($row['sp'] ?? 0),
            'skill_point' => (int) ($row['skill_point'] ?? 0),
            'members' => $this->members($guildId),
            'comments' => $this->comments($guildId),
            'wars' => $this->wars($guildId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateAdmin(int $guildId, array $input): void
    {
        if ($guildId < 1 || !$this->schemaTableExists('guild')) {
            throw new \InvalidArgumentException('admin.guilds.not_found');
        }

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || strlen($name) > 12) {
            throw new \InvalidArgumentException('admin.guilds.invalid_name');
        }

        $this->db()->execute(
            'UPDATE `guild`
             SET name = ?, level = ?, exp = ?, gold = ?, ladder_point = ?,
                 win = ?, draw = ?, loss = ?
             WHERE id = ?',
            [
                $name,
                max(0, (int) ($input['level'] ?? 0)),
                max(0, (int) ($input['exp'] ?? 0)),
                max(0, (int) ($input['gold'] ?? 0)),
                max(0, (int) ($input['ladder_point'] ?? 0)),
                max(0, (int) ($input['win'] ?? 0)),
                max(0, (int) ($input['draw'] ?? 0)),
                max(0, (int) ($input['loss'] ?? 0)),
                $guildId,
            ],
        );
    }

    public function kickMember(int $guildId, int $playerId): bool
    {
        if ($guildId < 1 || $playerId < 1 || !$this->schemaTableExists('guild') || !$this->schemaTableExists('guild_member')) {
            return false;
        }

        $masterId = (int) $this->db()->fetchColumn(
            'SELECT master FROM `guild` WHERE id = ? LIMIT 1',
            [$guildId],
        );

        if ($masterId === $playerId) {
            throw new \InvalidArgumentException('admin.guilds.cannot_kick_master');
        }

        return $this->db()->execute(
            'DELETE FROM `guild_member` WHERE guild_id = ? AND pid = ?',
            [$guildId, $playerId],
        ) > 0;
    }

    public function deleteComment(int $guildId, int $commentId): bool
    {
        if ($guildId < 1 || $commentId < 1 || !$this->schemaTableExists('guild_comment')) {
            return false;
        }

        return $this->db()->execute(
            'DELETE FROM `guild_comment` WHERE id = ? AND guild_id = ?',
            [$commentId, $guildId],
        ) > 0;
    }

    public function dissolve(int $guildId): bool
    {
        if ($guildId < 1 || !$this->schemaTableExists('guild')) {
            return false;
        }

        $this->db()->beginTransaction();

        try {
            if ($this->schemaTableExists('guild_comment')) {
                $this->db()->execute('DELETE FROM `guild_comment` WHERE guild_id = ?', [$guildId]);
            }

            if ($this->schemaTableExists('guild_grade')) {
                $this->db()->execute('DELETE FROM `guild_grade` WHERE guild_id = ?', [$guildId]);
            }

            if ($this->schemaTableExists('guild_member')) {
                $this->db()->execute('DELETE FROM `guild_member` WHERE guild_id = ?', [$guildId]);
            }

            if ($this->schemaTableExists('guild_war')) {
                $this->db()->execute('DELETE FROM `guild_war` WHERE id_from = ? OR id_to = ?', [$guildId, $guildId]);
            }

            if ($this->schemaTableExists('guild_war_bet')) {
                $this->db()->execute('DELETE FROM `guild_war_bet` WHERE guild = ?', [$guildId]);
            }

            if ($this->schemaTableExists('guild_war_reservation')) {
                $this->db()->execute(
                    'DELETE FROM `guild_war_reservation` WHERE guild1 = ? OR guild2 = ?',
                    [$guildId, $guildId],
                );
            }

            $deleted = $this->db()->execute('DELETE FROM `guild` WHERE id = ?', [$guildId]) > 0;
            $this->db()->commit();

            return $deleted;
        } catch (\Throwable $exception) {
            $this->db()->rollBack();

            throw $exception;
        }
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   level: int,
     *   exp: int,
     *   gold: int,
     *   ladder_point: int,
     *   win: int,
     *   draw: int,
     *   loss: int,
     *   master_id: int,
     *   master_name: string|null,
     *   is_master: bool,
     *   grade: int,
     *   grade_name: string|null,
     *   is_general: bool,
     *   offer: int,
     *   members: list<array<string, mixed>>,
     *   comments: list<array<string, mixed>>
     * }|null
     */
    public function profileForPlayer(int $playerId): ?array
    {
        if ($playerId < 1 || !$this->schemaTableExists('guild') || !$this->schemaTableExists('guild_member')) {
            return null;
        }

        $gradeJoin = $this->gradeJoin();
        $row = $this->db()->fetch(
            'SELECT g.id, g.name, g.level, g.exp, g.gold, g.ladder_point, g.win, g.draw, g.loss,
                    g.master, p.name AS master_name,
                    gm.grade, gm.is_general, gm.offer, ' . $gradeJoin['select'] . '
             FROM `guild_member` gm
             INNER JOIN `guild` g ON g.id = gm.guild_id
             LEFT JOIN `player` p ON p.id = g.master
             ' . $gradeJoin['join'] . '
             WHERE gm.pid = ?
             LIMIT 1',
            [$playerId],
        );

        if ($row === null) {
            return null;
        }

        $guildId = (int) $row['id'];
        $masterId = (int) $row['master'];

        return [
            'id' => $guildId,
            'name' => (string) $row['name'],
            'level' => (int) ($row['level'] ?? 0),
            'exp' => (int) ($row['exp'] ?? 0),
            'gold' => (int) ($row['gold'] ?? 0),
            'ladder_point' => (int) ($row['ladder_point'] ?? 0),
            'win' => (int) ($row['win'] ?? 0),
            'draw' => (int) ($row['draw'] ?? 0),
            'loss' => (int) ($row['loss'] ?? 0),
            'master_id' => $masterId,
            'master_name' => $row['master_name'] !== null ? (string) $row['master_name'] : null,
            'is_master' => $masterId === $playerId,
            'grade' => (int) ($row['grade'] ?? 0),
            'grade_name' => $row['grade_name'] !== null ? trim((string) $row['grade_name']) : null,
            'is_general' => (int) ($row['is_general'] ?? 0) === 1,
            'offer' => (int) ($row['offer'] ?? 0),
            'members' => $this->members($guildId),
            'comments' => $this->comments($guildId),
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        if ($query->q === null || $query->q === '') {
            return ['', []];
        }

        $like = '%' . $query->q . '%';

        if (ctype_digit($query->q)) {
            return [
                ' WHERE g.id = ? OR g.name LIKE ? OR p.name LIKE ?',
                [(int) $query->q, $like, $like],
            ];
        }

        return [
            ' WHERE g.name LIKE ? OR p.name LIKE ?',
            [$like, $like],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function members(int $guildId): array
    {
        if (!$this->schemaTableExists('guild_member')) {
            return [];
        }

        $gradeJoin = $this->gradeJoin();
        $rows = $this->revealAll(
            $this->db()->fetchAll(
                'SELECT gm.pid, gm.grade, gm.is_general, gm.offer,
                        p.name, p.level, p.job, p.skill_group,
                        ' . $gradeJoin['select'] . '
                 FROM `guild_member` gm
                 LEFT JOIN `player` p ON p.id = gm.pid
                 ' . $gradeJoin['join'] . '
                 WHERE gm.guild_id = ?
                 ORDER BY gm.grade ASC, p.name ASC',
                [$guildId],
            ),
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['pid'],
                'name' => $row['name'] !== null ? (string) $row['name'] : null,
                'level' => (int) ($row['level'] ?? 0),
                'job' => (int) ($row['job'] ?? 0),
                'skill_group' => (int) ($row['skill_group'] ?? 0),
                'grade' => (int) ($row['grade'] ?? 0),
                'grade_name' => $row['grade_name'] !== null ? trim((string) $row['grade_name']) : null,
                'is_general' => (int) ($row['is_general'] ?? 0) === 1,
                'offer' => (int) ($row['offer'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function comments(int $guildId): array
    {
        if (!$this->schemaTableExists('guild_comment')) {
            return [];
        }

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT id, name, notice, content, time
                 FROM `guild_comment`
                 WHERE guild_id = ?
                 ORDER BY time DESC, id DESC
                 LIMIT 50',
                [$guildId],
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wars(int $guildId): array
    {
        if (!$this->schemaTableExists('guild_war_reservation')) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT r.id, r.guild1, r.guild2, r.time, r.type, r.warprice, r.initscore,
                    r.started, r.winner, r.power1, r.power2, r.handicap, r.result1, r.result2,
                    g1.name AS guild1_name, g2.name AS guild2_name
             FROM `guild_war_reservation` r
             LEFT JOIN `guild` g1 ON g1.id = r.guild1
             LEFT JOIN `guild` g2 ON g2.id = r.guild2
             WHERE r.guild1 = ? OR r.guild2 = ?
             ORDER BY r.time DESC, r.id DESC
             LIMIT 50',
            [$guildId, $guildId],
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'guild1' => (int) ($row['guild1'] ?? 0),
                'guild2' => (int) ($row['guild2'] ?? 0),
                'guild1_name' => $row['guild1_name'] !== null ? (string) $row['guild1_name'] : null,
                'guild2_name' => $row['guild2_name'] !== null ? (string) $row['guild2_name'] : null,
                'time' => $row['time'] ?? null,
                'type' => (int) ($row['type'] ?? 0),
                'warprice' => (int) ($row['warprice'] ?? 0),
                'initscore' => (int) ($row['initscore'] ?? 0),
                'started' => (int) ($row['started'] ?? 0) === 1,
                'winner' => (int) ($row['winner'] ?? -1),
                'power1' => (int) ($row['power1'] ?? 0),
                'power2' => (int) ($row['power2'] ?? 0),
                'handicap' => (int) ($row['handicap'] ?? 0),
                'result1' => (int) ($row['result1'] ?? 0),
                'result2' => (int) ($row['result2'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{select: string, join: string}
     */
    private function gradeJoin(): array
    {
        if (!$this->schemaTableExists('guild_grade')) {
            return [
                'select' => 'NULL AS grade_name',
                'join' => '',
            ];
        }

        return [
            'select' => 'gg.name AS grade_name',
            'join' => 'LEFT JOIN `guild_grade` gg ON gg.guild_id = gm.guild_id AND gg.grade = gm.grade',
        ];
    }
}
