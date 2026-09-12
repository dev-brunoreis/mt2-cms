<?php

declare(strict_types=1);

use Mt2Cms\Application;
use Mt2Cms\Auth\Auth;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Support\Database;

/**
 * @return callable(Application): void
 */
return static function (Application $app): void {
    $app->translator = new Translator(BASE_DIR . '/lang', $app->locales->resolve('en'));
    $app->theme = $app->createThemeEngine('default', true, false);
    $app->auth = new Auth(new AccountRepository(new Database(['requirePassword' => false])));
};
