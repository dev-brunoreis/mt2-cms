<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Captcha;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

class CaptchaController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function publicSvg(): Response
    {
        $captcha = new Captcha('public');
        $svg = $captcha->svg();

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ], false);
    }

    public function adminSvg(): Response
    {
        $captcha = new Captcha('admin');
        $svg = $captcha->svg();

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ], false);
    }
}
