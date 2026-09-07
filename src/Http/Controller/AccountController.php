<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class AccountController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();
        $players = $accountId !== null
            ? $this->players->findByAccountId($accountId)
            : [];

        return $this->view('account', [
            'title' => 'My Account',
            'account' => $this->auth->user(),
            'players' => $players,
        ]);
    }
}
