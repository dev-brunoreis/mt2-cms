<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Theme\ThemeEngine;

class GameIconController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private GameIconService $icons,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function show(string $kind, string $id): Response
    {
        if ($kind !== GameIconService::KIND_ITEM && $kind !== GameIconService::KIND_FACE) {
            return Response::notFound('');
        }

        $png = $this->icons->png($kind, (int) $id);

        if ($png === null) {
            return new Response('', 404, ['Content-Type' => 'image/png']);
        }

        return Response::png($png);
    }
}
