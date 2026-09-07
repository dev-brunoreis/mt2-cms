<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

class LocaleController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private Locales $locales,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function update(): Response
    {
        $redirect = $this->locales->safeRedirect(
            is_string($_POST['redirect'] ?? null) ? $_POST['redirect'] : '/',
        );

        if (!$this->assertCsrf()) {
            return $this->redirect($redirect);
        }

        $locale = trim((string) ($_POST['locale'] ?? ''));

        if (!$this->locales->isSupported($locale)) {
            return $this->redirect($redirect);
        }

        return $this->redirect($redirect)->withCookie(Locales::COOKIE, $locale);
    }
}
