<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Referral\ReferralRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminReferralsController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private ReferralRepository $referrals,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->referrals->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->referrals->countForGrid($q),
            fn ($q) => $this->referrals->listForGrid($q),
        );

        return $this->adminView('referrals', 'pages/referrals.twig', [
            'title' => $this->t('admin.referrals.title'),
            'pageLead' => $this->t('admin.referrals.lead'),
            'grid' => $grid,
            'referralEnabled' => $this->settings->referralEnabled(),
            'rewardCash' => $this->settings->referralRewardCash(),
            'minLevel' => $this->settings->referralMinLevel(),
            'cap' => $this->settings->referralCap(),
            'formId' => 'admin-referral-settings-form',
            'saveLabel' => $this->t('admin.save'),
        ]);
    }

    public function saveSettings(): Response
    {
        if ($redirect = $this->requireAdminResource('game/referrals/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/referrals');
        }

        $before = [
            'referral_enabled' => $this->settings->referralEnabled(),
            'referral_reward_cash' => $this->settings->referralRewardCash(),
            'referral_min_level' => $this->settings->referralMinLevel(),
            'referral_cap' => $this->settings->referralCap(),
        ];

        $this->settings->setReferralEnabled(isset($_POST['referral_enabled']));
        $this->settings->setReferralRewardCash((int) ($_POST['referral_reward_cash'] ?? 0));
        $this->settings->setReferralMinLevel((int) ($_POST['referral_min_level'] ?? 10));
        $this->settings->setReferralCap((int) ($_POST['referral_cap'] ?? 0));

        $after = [
            'referral_enabled' => $this->settings->referralEnabled(),
            'referral_reward_cash' => $this->settings->referralRewardCash(),
            'referral_min_level' => $this->settings->referralMinLevel(),
            'referral_cap' => $this->settings->referralCap(),
        ];

        $this->auditChange('settings.referral_save', 'settings', null, $before, $after);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/game/referrals');
    }
}
