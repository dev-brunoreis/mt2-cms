<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Controller\Controller;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

/**
 * Admin-session locale switcher. Must POST under /admin/* so CSRF uses MT2ADMIN
 * (path /admin), not the public MT2CMS session used by POST /locale.
 */
class AdminLocaleController extends Controller
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
            is_string($_POST['redirect'] ?? null) ? $_POST['redirect'] : '/admin',
        );

        $path = explode('?', $redirect, 2)[0];

        if ($path !== '/admin' && !str_starts_with($path, '/admin/')) {
            $redirect = '/admin';
        }

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
