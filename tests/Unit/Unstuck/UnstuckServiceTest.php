<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Unstuck;

use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Unstuck\UnstuckRepository;
use Mt2Cms\Unstuck\UnstuckService;
use PHPUnit\Framework\TestCase;

final class UnstuckServiceTest extends TestCase
{
    public function testUnstuckRejectsOnlinePlayer(): void
    {
        $cooldowns = $this->createMock(UnstuckRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('unstuckEnabled')->willReturn(true);
        $players->method('hasPositionColumns')->willReturn(true);
        $players->method('findById')->willReturn([
            'id' => 10,
            'account_id' => 1,
            'last_play' => date('Y-m-d H:i:s'),
        ]);
        $settings->method('onlineWindowMinutes')->willReturn(15);

        $players->expects(self::never())->method('teleportTo');

        $service = new UnstuckService($cooldowns, $players, $settings);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unstuck.player_online');

        $service->unstuck(10, 1);
    }

    public function testUnstuckRejectsWrongAccount(): void
    {
        $cooldowns = $this->createMock(UnstuckRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('unstuckEnabled')->willReturn(true);
        $players->method('hasPositionColumns')->willReturn(true);
        $players->method('findById')->willReturn([
            'id' => 10,
            'account_id' => 99,
            'last_play' => '',
        ]);

        $players->expects(self::never())->method('teleportTo');

        $service = new UnstuckService($cooldowns, $players, $settings);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unstuck.player_not_found');

        $service->unstuck(10, 1);
    }

    public function testUnstuckRejectsWhenOnCooldown(): void
    {
        $cooldowns = $this->createMock(UnstuckRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('unstuckEnabled')->willReturn(true);
        $players->method('hasPositionColumns')->willReturn(true);
        $players->method('findById')->willReturn([
            'id' => 10,
            'account_id' => 1,
            'last_play' => '',
        ]);
        $settings->method('onlineWindowMinutes')->willReturn(15);
        $cooldowns->method('lastUnstuckAt')->willReturn(date('Y-m-d H:i:s'));
        $settings->method('unstuckCooldownMinutes')->willReturn(60);

        $players->expects(self::never())->method('teleportTo');

        $service = new UnstuckService($cooldowns, $players, $settings);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unstuck.cooldown');

        $service->unstuck(10, 1);
    }

    public function testAdminBypassSkipsCooldownAndOnlineCheck(): void
    {
        $cooldowns = $this->createMock(UnstuckRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $players->method('hasPositionColumns')->willReturn(true);
        $players->method('findById')->willReturn([
            'id' => 10,
            'account_id' => 1,
            'last_play' => date('Y-m-d H:i:s'),
        ]);
        $players->method('findEmpireByAccountId')->willReturn(1);
        $settings->method('unstuckSpawnForEmpire')->willReturn([
            'map_index' => 1,
            'x' => 100,
            'y' => 200,
        ]);
        $players->expects(self::once())
            ->method('teleportTo')
            ->with(10, 1, 1, 100, 200)
            ->willReturn(true);
        $cooldowns->expects(self::never())->method('recordCooldown');

        $service = new UnstuckService($cooldowns, $players, $settings);
        $service->unstuck(10, 1, adminBypass: true);
    }
}
