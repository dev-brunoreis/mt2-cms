<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

abstract class Controller
{
    public function __construct(
        protected ThemeEngine $theme,
        protected Auth $auth,
        protected Csrf $csrf,
        protected Translator $translator,
    ) {
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    protected function t(string $key, array $replace = []): string
    {
        return $this->translator->get($key, $replace);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $layout, array $data = [], int $status = 200): Response
    {
        $data = array_merge([
            'auth' => [
                'check' => $this->auth->check(),
                'login' => $this->auth->login(),
                'user' => $this->auth->user(),
            ],
            'csrf' => $this->csrf->token(),
            'flash' => $this->pullFlash(),
        ], $data);

        return Response::html($this->theme->render($layout, $data), $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    protected function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : null;
    }

    protected function requireAuth(): ?Response
    {
        if ($this->auth->check()) {
            return null;
        }

        $this->flash('error', $this->t('auth.login_required'));

        return $this->redirect('/login');
    }

    protected function requireGuest(): ?Response
    {
        if (!$this->auth->check()) {
            return null;
        }

        return $this->redirect('/account');
    }

    protected function assertCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? null;

        return $this->csrf->validate(is_string($token) ? $token : null);
    }
}
