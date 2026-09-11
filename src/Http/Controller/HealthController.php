<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Model\Database;
use Mt2Cms\Support\Log;
use Mt2Cms\Theme\ThemeEngine;

class HealthController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private Database $cmsDb,
        private Database $gameDb,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        try {
            $this->cmsDb->fetchColumn('SELECT 1');
            $this->gameDb->fetchColumn('SELECT 1');

            return Response::html('ok', 200);
        } catch (\Throwable $e) {
            Log::error('health', 'Health check failed', $e);

            return Response::html('Service temporarily unavailable', 503);
        }
    }
}
