<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

use Mt2Cms\Repository\AccountRepository;

class Auth
{
    private const SESSION_ID = 'account_id';
    private const SESSION_LOGIN = 'account_login';

    public function __construct(private AccountRepository $accounts)
    {
    }

    public function attempt(string $login, string $password): bool
    {
        $account = $this->accounts->authenticate($login, $password);

        if ($account === null) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION[self::SESSION_ID] = (int) $account['id'];
        $_SESSION[self::SESSION_LOGIN] = (string) $account['login'];

        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION[self::SESSION_ID]) && (int) $_SESSION[self::SESSION_ID] > 0;
    }

    public function id(): ?int
    {
        if (!$this->check()) {
            return null;
        }

        return (int) $_SESSION[self::SESSION_ID];
    }

    public function user(): ?array
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return $this->accounts->findById($id);
    }

    public function login(): ?string
    {
        if (!$this->check()) {
            return null;
        }

        return (string) ($_SESSION[self::SESSION_LOGIN] ?? '');
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_ID], $_SESSION[self::SESSION_LOGIN]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_start();
        }
    }
}
