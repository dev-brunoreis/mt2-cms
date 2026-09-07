<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function validate(?string $token): bool
    {
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if ($sessionToken === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
