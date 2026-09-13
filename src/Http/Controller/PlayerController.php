<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\InventoryLayout;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\ItemRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Service\UnstuckService;
use Mt2Cms\Theme\ThemeEngine;

class PlayerController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PlayerRepository $players,
        private GuildRepository $guilds,
        private UnstuckService $unstuck,
        private SettingsService $settings,
        private ItemRepository $items,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function show(string $name): Response
    {
        $player = $this->players->findPublicByName($name);

        if ($player === null) {
            return $this->view('player', [
                'title' => $this->t('player.not_found_title'),
                'player' => null,
                'notFound' => true,
                'guild' => null,
                'marriage' => null,
                'levelRank' => null,
                'playtimeRank' => null,
                'online' => false,
                'unstuckAvailable' => false,
                'unstuckState' => null,
                'onlineWindowMinutes' => $this->settings->onlineWindowMinutes(),
                'equipmentLayout' => null,
            ], 404);
        }

        $playerId = (int) ($player['id'] ?? 0);
        $minutes = $this->settings->onlineWindowMinutes();
        $equipmentLayout = null;

        if ($this->settings->publicPlayerEquipment() && $playerId > 0) {
            $equipmentLayout = InventoryLayout::forPublicProfile(
                $this->items->equipmentForCharacter($playerId),
            );
        }

        return $this->view('player', [
            'title' => $player['name'],
            'player' => $player,
            'notFound' => false,
            'guild' => $this->guilds->publicForPlayer($playerId),
            'marriage' => $this->publicMarriage($playerId),
            'levelRank' => $this->players->levelRank($playerId),
            'playtimeRank' => $this->players->playtimeRank($playerId),
            'online' => $this->isRecentlyActive((string) ($player['last_play'] ?? ''), $minutes),
            'equipmentLayout' => $equipmentLayout,
            ...$this->ownUnstuckContext($playerId),
        ]);
    }

    /**
     * @return array{partner_name: string}|null
     */
    private function publicMarriage(int $playerId): ?array
    {
        $marriage = $this->players->findMarriageForPlayer($playerId);
        $partner = trim((string) ($marriage['partner_name'] ?? ''));

        if ($marriage === null || !($marriage['is_married'] ?? false) || $partner === '') {
            return null;
        }

        return ['partner_name' => $partner];
    }

    private function isRecentlyActive(string $lastPlay, int $minutes): bool
    {
        $raw = trim($lastPlay);

        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return false;
        }

        try {
            $at = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return false;
        }

        return $at->getTimestamp() >= time() - (max(1, $minutes) * 60);
    }

    /**
     * @return array{unstuckAvailable: bool, unstuckState: array{offline: bool, cooldown_seconds: int}|null, onlineWindowMinutes: int}
     */
    private function ownUnstuckContext(int $playerId): array
    {
        $accountId = $this->auth->id();
        $onlineWindowMinutes = $this->settings->onlineWindowMinutes();

        if ($accountId === null || $playerId < 1 || !$this->unstuck->isAvailable()) {
            return [
                'unstuckAvailable' => false,
                'unstuckState' => null,
                'onlineWindowMinutes' => $onlineWindowMinutes,
            ];
        }

        $owned = $this->players->findById($playerId);

        if ($owned === null || (int) ($owned['account_id'] ?? 0) !== $accountId) {
            return [
                'unstuckAvailable' => false,
                'unstuckState' => null,
                'onlineWindowMinutes' => $onlineWindowMinutes,
            ];
        }

        return [
            'unstuckAvailable' => true,
            'unstuckState' => [
                'offline' => $this->unstuck->isOffline($playerId),
                'cooldown_seconds' => $this->unstuck->cooldownRemainingSeconds($playerId),
            ],
            'onlineWindowMinutes' => $onlineWindowMinutes,
        ];
    }
}
