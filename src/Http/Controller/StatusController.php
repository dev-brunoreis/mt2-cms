<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class StatusController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PlayerRepository $players,
        private ServerChannelRepository $channels,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        $minutes = $this->settings->onlineWindowMinutes();

        return $this->view('status', [
            'title' => $this->t('status.title'),
            'playersOnline' => $this->players->countActiveSinceMinutes($minutes),
            'accountsOnline' => $this->players->countAccountsActiveSinceMinutes($minutes),
            'windowMinutes' => $minutes,
            'channels' => $this->channels->listEnabled(),
        ]);
    }
}
