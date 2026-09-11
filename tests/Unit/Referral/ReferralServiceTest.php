<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Referral;

use Mt2Cms\Referral\ReferralRepository;
use Mt2Cms\Referral\ReferralService;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class ReferralServiceTest extends TestCase
{
    public function testLinkOnRegisterDoesNothingWhenDisabled(): void
    {
        $referrals = $this->createMock(ReferralRepository::class);
        $accounts = $this->createMock(AccountRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('referralEnabled')->willReturn(false);
        $referrals->expects(self::never())->method('createReferral');

        $service = new ReferralService($referrals, $accounts, $players, $settings);
        $service->linkOnRegister(5, 'FRIEND1');
    }

    public function testLinkOnRegisterIgnoresSelfReferral(): void
    {
        $referrals = $this->createMock(ReferralRepository::class);
        $accounts = $this->createMock(AccountRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('referralEnabled')->willReturn(true);
        $referrals->method('findAccountIdByCode')->with('SELF')->willReturn(5);
        $referrals->expects(self::never())->method('createReferral');
        $referrals->method('findCodeByAccountId')->willReturn(null);
        $referrals->method('codeExists')->willReturn(false);
        $referrals->expects(self::once())->method('assignCode');

        $service = new ReferralService($referrals, $accounts, $players, $settings);
        $service->linkOnRegister(5, 'SELF');
    }

    public function testProcessPendingRewardReturnsFalseWhenBelowMinLevel(): void
    {
        $referrals = $this->createMock(ReferralRepository::class);
        $accounts = $this->createMock(AccountRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('referralEnabled')->willReturn(true);
        $referrals->method('findPendingByReferredId')->with(20)->willReturn([
            'id' => 1,
            'referrer_id' => 10,
        ]);
        $settings->method('referralCap')->willReturn(0);
        $referrals->method('countRewardedByReferrer')->willReturn(0);
        $players->method('maxLevelByAccountId')->with(20)->willReturn(5);
        $settings->method('referralMinLevel')->willReturn(30);

        $accounts->expects(self::never())->method('creditCash');

        $service = new ReferralService($referrals, $accounts, $players, $settings);
        self::assertFalse($service->processPendingReward(20));
    }

    public function testProcessPendingRewardCreditsReferrerAndMarksRewarded(): void
    {
        $referrals = $this->createMock(ReferralRepository::class);
        $accounts = $this->createMock(AccountRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('referralEnabled')->willReturn(true);
        $referrals->method('findPendingByReferredId')->with(20)->willReturn([
            'id' => 3,
            'referrer_id' => 10,
        ]);
        $settings->method('referralCap')->willReturn(0);
        $referrals->method('countRewardedByReferrer')->willReturn(0);
        $players->method('maxLevelByAccountId')->with(20)->willReturn(50);
        $settings->method('referralMinLevel')->willReturn(30);
        $settings->method('referralRewardCash')->willReturn(100);
        $accounts->expects(self::once())->method('creditCash')->with(10, 100)->willReturn(true);
        $referrals->expects(self::once())->method('markRewarded')->with(3)->willReturn(true);

        $service = new ReferralService($referrals, $accounts, $players, $settings);
        self::assertTrue($service->processPendingReward(20));
    }

    public function testProcessPendingRewardRespectsCap(): void
    {
        $referrals = $this->createMock(ReferralRepository::class);
        $accounts = $this->createMock(AccountRepository::class);
        $players = $this->createMock(PlayerRepository::class);
        $settings = $this->createMock(SettingsService::class);

        $settings->method('referralEnabled')->willReturn(true);
        $referrals->method('findPendingByReferredId')->willReturn([
            'id' => 3,
            'referrer_id' => 10,
        ]);
        $settings->method('referralCap')->willReturn(5);
        $referrals->method('countRewardedByReferrer')->with(10)->willReturn(5);

        $accounts->expects(self::never())->method('creditCash');

        $service = new ReferralService($referrals, $accounts, $players, $settings);
        self::assertFalse($service->processPendingReward(20));
    }
}
