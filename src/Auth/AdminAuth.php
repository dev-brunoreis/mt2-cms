<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

use Mt2Cms\Repository\AdminRepository;

class AdminAuth
{
    private const SESSION_ID = 'cms_admin_id';
    private const SESSION_LOGIN = 'cms_admin_login';

    public function __construct(private AdminRepository $admins)
    {
    }

    public function attempt(string $login, string $password): bool
    {
        $admin = $this->admins->authenticate($login, $password);

        if ($admin === null) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION[self::SESSION_ID] = (int) $admin['id'];
        $_SESSION[self::SESSION_LOGIN] = (string) $admin['login'];

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

    public function login(): ?string
    {
        if (!$this->check()) {
            return null;
        }

        return (string) ($_SESSION[self::SESSION_LOGIN] ?? '');
    }

    public function user(): ?array
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return $this->admins->findById($id);
    }

    public function role(): string
    {
        $user = $this->user();

        if ($user === null) {
            return 'super';
        }

        return (string) ($user['role'] ?? \Mt2Cms\Admin\AdminPermissions::ROLE_SUPER);
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_ID], $_SESSION[self::SESSION_LOGIN]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
