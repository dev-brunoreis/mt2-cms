<?php

declare(strict_types=1);

namespace Mt2Cms\Ban;

use Mt2Cms\Repository\AccountRepository;

class BanService
{
    public function __construct(
        private BanRepository $bans,
        private AccountRepository $accounts,
    ) {
    }

    public function apply(int $accountId, string $login, string $reason, ?string $expiresAt, ?int $adminId): void
    {
        $this->bans->create($accountId, $login, $reason, $expiresAt, $adminId);
        $this->accounts->block($accountId);
    }

    public function lift(int $banId, int $accountId): void
    {
        $this->bans->lift($banId);
        $this->accounts->unblock($accountId);
    }

    public function liftAccount(int $accountId): void
    {
        $this->bans->liftByAccountId($accountId);
        $this->accounts->unblock($accountId);
    }

    /**
     * Expire temporary bans and unblock accounts when no other active ban exists.
     */
    public function refreshAccount(int $accountId): void
    {
        $this->bans->expireDue();
        $active = $this->bans->findActiveByAccountId($accountId);

        if ($active === null) {
            $account = $this->accounts->findById($accountId);

            if ($account !== null && (string) ($account['status'] ?? '') === 'BLOCK') {
                $this->accounts->unblock($accountId);
            }

            return;
        }

        $expiresAt = $active['expires_at'] ?? null;

        if ($expiresAt !== null && strtotime((string) $expiresAt) < time()) {
            $this->bans->lift((int) $active['id']);
            $this->accounts->unblock($accountId);
        }
    }

    public function isBanned(int $accountId): bool
    {
        $this->refreshAccount($accountId);
        $active = $this->bans->findActiveByAccountId($accountId);

        if ($active !== null) {
            return true;
        }

        $account = $this->accounts->findById($accountId);

        return $account !== null && (string) ($account['status'] ?? '') === 'BLOCK';
    }
}
