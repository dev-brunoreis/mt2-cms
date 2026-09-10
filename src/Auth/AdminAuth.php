<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

use Mt2Cms\Repository\AdminRepository;

class AdminAuth
{
    private const SESSION_ID = 'cms_admin_id';
    private const SESSION_LOGIN = 'cms_admin_login';
    private const SESSION_PENDING = 'admin_2fa_pending';
    private const SESSION_ENROLL_SECRET = 'admin_2fa_enroll_secret';

    public function __construct(private AdminRepository $admins)
    {
    }

    /**
     * @return array{id: int, login: string, totp_enabled: bool}|null
     */
    public function verifyPassword(string $login, string $password): ?array
    {
        return $this->admins->authenticate($login, $password);
    }

    /**
     * @param array{id: int, login: string} $admin
     */
    public function completeLogin(array $admin): void
    {
        $this->clearPendingTwoFactor();

        session_regenerate_id(true);

        $_SESSION[self::SESSION_ID] = (int) $admin['id'];
        $_SESSION[self::SESSION_LOGIN] = (string) $admin['login'];
    }

    public function attempt(string $login, string $password): bool
    {
        $admin = $this->verifyPassword($login, $password);

        if ($admin === null) {
            return false;
        }

        $this->completeLogin($admin);

        return true;
    }

    public function setPendingTwoFactor(int $adminId, string $login): void
    {
        session_regenerate_id(true);

        $_SESSION[self::SESSION_PENDING] = [
            'id' => $adminId,
            'login' => $login,
            'expires' => time() + 300,
        ];
    }

    /**
     * @return array{id: int, login: string, expires: int}|null
     */
    public function pendingTwoFactor(): ?array
    {
        $pending = $_SESSION[self::SESSION_PENDING] ?? null;

        if (!is_array($pending)) {
            return null;
        }

        if ((int) ($pending['expires'] ?? 0) < time()) {
            $this->clearPendingTwoFactor();

            return null;
        }

        return [
            'id' => (int) ($pending['id'] ?? 0),
            'login' => (string) ($pending['login'] ?? ''),
            'expires' => (int) ($pending['expires'] ?? 0),
        ];
    }

    public function clearPendingTwoFactor(): void
    {
        unset($_SESSION[self::SESSION_PENDING]);
    }

    public function setEnrollSecret(string $secret): void
    {
        $_SESSION[self::SESSION_ENROLL_SECRET] = $secret;
    }

    public function pullEnrollSecret(): ?string
    {
        $secret = $_SESSION[self::SESSION_ENROLL_SECRET] ?? null;
        unset($_SESSION[self::SESSION_ENROLL_SECRET]);

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function peekEnrollSecret(): ?string
    {
        $secret = $_SESSION[self::SESSION_ENROLL_SECRET] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
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
        unset(
            $_SESSION[self::SESSION_ID],
            $_SESSION[self::SESSION_LOGIN],
            $_SESSION[self::SESSION_PENDING],
            $_SESSION[self::SESSION_ENROLL_SECRET],
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_start();
        }
    }
}
