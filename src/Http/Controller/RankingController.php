<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class RankingController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PlayerRepository $players,
        private GuildRepository $guilds,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        $tab = trim((string) ($_GET['tab'] ?? 'level'));
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        if ($tab === 'playtime') {
            $total = $this->players->countPlaytimeRanking($query);
            $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $ranking = $this->players->listPlaytimeRanking($page, self::PER_PAGE, $query);
        } elseif ($tab === 'guilds') {
            $total = $this->guilds->countPublicRanking($query);
            $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $ranking = $this->guilds->listPublicRanking($page, self::PER_PAGE, $query);
        } else {
            $tab = 'level';
            $total = $this->players->countRanking($query);
            $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $ranking = $this->players->listRanking($page, self::PER_PAGE, $query);
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return $this->view('ranking', [
            'title' => $this->t('nav.ranking'),
            'tab' => $tab,
            'ranking' => $ranking,
            'query' => $q,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'totalPages' => $totalPages,
            'offset' => $offset,
        ]);
    }
}
