<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class PlayerController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PlayerRepository $players,
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
            ], 404);
        }

        return $this->view('player', [
            'title' => $player['name'],
            'player' => $player,
            'notFound' => false,
        ]);
    }
}
