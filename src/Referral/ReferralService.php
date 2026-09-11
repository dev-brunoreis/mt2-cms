<?php

declare(strict_types=1);

namespace Mt2Cms\Referral;

use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\SettingsService;

class ReferralService
{
    public function __construct(
        private ReferralRepository $referrals,
        private AccountRepository $accounts,
        private PlayerRepository $players,
        private SettingsService $settings,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->referralEnabled();
    }

    public function ensureCodeForAccount(int $accountId): string
    {
        $existing = $this->referrals->findCodeByAccountId($accountId);

        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        do {
            $code = $this->generateCode();
        } while ($this->referrals->codeExists($code));

        $this->referrals->assignCode($accountId, $code);

        return $code;
    }

    public function linkOnRegister(int $referredAccountId, ?string $referralCode): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $code = trim((string) $referralCode);

        if ($code === '') {
            $this->ensureCodeForAccount($referredAccountId);

            return;
        }

        $referrerId = $this->referrals->findAccountIdByCode($code);

        if ($referrerId === null || $referrerId < 1 || $referrerId === $referredAccountId) {
            $this->ensureCodeForAccount($referredAccountId);

            return;
        }

        try {
            $this->referrals->createReferral($referrerId, $referredAccountId);
        } catch (\Throwable) {
            // Duplicate referred_id or DB error — still give the new account its own code.
        }

        $this->ensureCodeForAccount($referredAccountId);
    }

    public function processPendingReward(int $referredAccountId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $referral = $this->referrals->findPendingByReferredId($referredAccountId);

        if ($referral === null) {
            return false;
        }

        $referrerId = (int) ($referral['referrer_id'] ?? 0);
        $referralId = (int) ($referral['id'] ?? 0);

        if ($referrerId < 1 || $referralId < 1) {
            return false;
        }

        $cap = $this->settings->referralCap();

        if ($cap > 0 && $this->referrals->countRewardedByReferrer($referrerId) >= $cap) {
            return false;
        }

        $maxLevel = $this->players->maxLevelByAccountId($referredAccountId);
        $minLevel = $this->settings->referralMinLevel();

        if ($maxLevel < $minLevel) {
            return false;
        }

        $cash = $this->settings->referralRewardCash();

        if ($cash < 1) {
            return false;
        }

        if (!$this->accounts->creditCash($referrerId, $cash)) {
            return false;
        }

        return $this->referrals->markRewarded($referralId);
    }

    private function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 8; ++$i) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
