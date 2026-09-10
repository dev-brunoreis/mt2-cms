<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Theme\ThemeEngine;

class HomeController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private NewsRepository $news,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        return $this->view('home', [
            'title' => $this->t('nav.home'),
            'posts' => $this->news->latestPublished(5),
        ]);
    }
}
