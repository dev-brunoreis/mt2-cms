<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class GuildRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
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
     * @return list<array<string, mixed>>
     */
    private function members(int $guildId): array
    {
        $gradeJoin = $this->gradeJoin();
        $rows = $this->revealAll(
            $this->db()->fetchAll(
                'SELECT gm.pid, gm.grade, gm.is_general, gm.offer,
                        p.name, p.level, p.job,
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
                'grade' => (int) ($row['grade'] ?? 0),
                'grade_name' => $row['grade_name'] !== null ? trim((string) $row['grade_name']) : null,
                'is_general' => (int) ($row['is_general'] ?? 0) === 1,
                'offer' => (int) ($row['offer'] ?? 0),
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
                 LIMIT 20',
                [$guildId],
            ),
        );
    }
}
