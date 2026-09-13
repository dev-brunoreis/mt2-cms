<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Service\SeoService;
use Mt2Cms\Theme\ThemeEngine;

class SeoController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private SeoService $seo,
        private NewsRepository $news,
        private EventRepository $events,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function robots(): Response
    {
        return Response::text($this->seo->robotsTxt());
    }

    public function sitemap(): Response
    {
        return Response::xml($this->seo->sitemapXml(
            $this->news->listPublishedForSitemap(),
            $this->events->listPublishedForSitemap(),
        ));
    }
}
