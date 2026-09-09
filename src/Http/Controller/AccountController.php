<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class AccountController extends Controller
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

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = $this->auth->id();
        $account = $this->auth->user();
        $players = $accountId !== null
            ? $this->players->findByAccountId($accountId)
            : [];

        if ($account !== null && $accountId !== null) {
            $account['empire'] = $this->players->findEmpireByAccountId($accountId);
        }

        return $this->view('account', [
            'title' => $this->t('account.title'),
            'account' => $account,
            'players' => $players,
        ]);
    }
}
