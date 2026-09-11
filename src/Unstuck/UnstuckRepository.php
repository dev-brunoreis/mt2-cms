<?php

declare(strict_types=1);

namespace Mt2Cms\Unstuck;

use Mt2Cms\Repository\Repository;

class UnstuckRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function recordCooldown(int $playerId, int $accountId): void
    {
        $this->db()->execute(
            'INSERT INTO cms_unstuck_cooldowns (player_id, account_id, unstuck_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE unstuck_at = NOW(), account_id = VALUES(account_id)',
            [$playerId, $accountId],
        );
    }

    public function lastUnstuckAt(int $playerId): ?string
    {
        $row = $this->db()->fetch(
            'SELECT unstuck_at FROM cms_unstuck_cooldowns WHERE player_id = ?',
            [$playerId],
        );

        if ($row === null) {
            return null;
        }

        return (string) ($row['unstuck_at'] ?? '');
    }
}
