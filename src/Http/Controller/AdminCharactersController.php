<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

class AdminCharactersController extends AdminController
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->players->countForAdmin($query);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('characters', 'pages/characters.twig', [
            'title' => $this->t('admin.characters.title'),
            'pageLead' => $this->t('admin.characters.lead'),
            'characters' => $this->players->listForAdmin($page, self::PER_PAGE, $query),
            'query' => $q,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function show(string $id): Response
    {
        $character = $this->players->findForAdmin((int) $id);

        if ($character === null) {
            $this->flash('error', $this->t('admin.characters.not_found'));

            return $this->redirect('/admin/characters');
        }

        return $this->adminView('characters', 'pages/character.twig', [
            'title' => $this->t('admin.characters.view_title', ['name' => (string) $character['name']]),
            'pageLead' => $this->t('admin.characters.view_lead'),
            'character' => $character,
        ]);
    }
}
