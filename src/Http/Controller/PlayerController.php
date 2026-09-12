<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
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
        private UnstuckService $unstuck,
        private SettingsService $settings,
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
                'unstuckAvailable' => false,
                'unstuckState' => null,
                'onlineWindowMinutes' => $this->settings->onlineWindowMinutes(),
            ], 404);
        }

        return $this->view('player', [
            'title' => $player['name'],
            'player' => $player,
            'notFound' => false,
            ...$this->ownUnstuckContext((int) ($player['id'] ?? 0)),
        ]);
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
