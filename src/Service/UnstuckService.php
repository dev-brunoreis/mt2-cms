<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Repository\UnstuckRepository;

class UnstuckService
{
    public function __construct(
        private UnstuckRepository $cooldowns,
        private PlayerRepository $players,
        private SettingsService $settings,
    ) {
    }

    public function hasPositionColumns(): bool
    {
        return $this->players->hasPositionColumns();
    }

    public function isAvailable(): bool
    {
        return $this->settings->unstuckEnabled() && $this->hasPositionColumns();
    }

    /**
     * @return array{map_index: int, x: int, y: int}|null
     */
    public function spawnForAccount(int $accountId): ?array
    {
        $empire = $this->players->findEmpireByAccountId($accountId);

        if ($empire < 1) {
            return null;
        }

        return $this->settings->unstuckSpawnForEmpire($empire);
    }

    public function isOnCooldown(int $playerId): bool
    {
        $last = $this->cooldowns->lastUnstuckAt($playerId);

        if ($last === null || $last === '') {
            return false;
        }

        $elapsed = time() - strtotime($last);

        return $elapsed < $this->settings->unstuckCooldownMinutes() * 60;
    }

    public function cooldownRemainingSeconds(int $playerId): int
    {
        $last = $this->cooldowns->lastUnstuckAt($playerId);

        if ($last === null || $last === '') {
            return 0;
        }

        $remaining = ($this->settings->unstuckCooldownMinutes() * 60) - (time() - strtotime($last));

        return max(0, $remaining);
    }

    public function isOffline(int $playerId): bool
    {
        $player = $this->players->findById($playerId);

        if ($player === null) {
            return false;
        }

        $lastPlay = (string) ($player['last_play'] ?? '');

        if ($lastPlay === '') {
            return true;
        }

        $threshold = time() - ($this->settings->onlineWindowMinutes() * 60);

        return strtotime($lastPlay) < $threshold;
    }

    public function unstuck(int $playerId, int $accountId, bool $adminBypass = false): void
    {
        if ($adminBypass) {
            if (!$this->hasPositionColumns()) {
                throw new \RuntimeException('unstuck.unavailable');
            }
        } elseif (!$this->isAvailable()) {
            throw new \RuntimeException('unstuck.unavailable');
        }

        $player = $this->players->findById($playerId);

        if ($player === null || (int) ($player['account_id'] ?? 0) !== $accountId) {
            throw new \InvalidArgumentException('unstuck.player_not_found');
        }

        if (!$adminBypass && !$this->isOffline($playerId)) {
            throw new \RuntimeException('unstuck.player_online');
        }

        if (!$adminBypass && $this->isOnCooldown($playerId)) {
            throw new \RuntimeException('unstuck.cooldown');
        }

        $spawn = $this->spawnForAccount($accountId);

        if ($spawn === null) {
            throw new \RuntimeException('unstuck.spawn_not_configured');
        }

        if (!$this->players->teleportTo($playerId, $accountId, $spawn['map_index'], $spawn['x'], $spawn['y'])) {
            throw new \RuntimeException('unstuck.failed');
        }

        if (!$adminBypass) {
            $this->cooldowns->recordCooldown($playerId, $accountId);
        }
    }
}
