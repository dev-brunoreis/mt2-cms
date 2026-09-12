<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\Totp;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AdminTotpRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminAccountSecurityController extends AdminController
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
        private AdminTotpRepository $totp,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function show(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $user = $this->adminAuth->user();
        $enabled = $user !== null && (int) ($user['totp_enabled'] ?? 0) === 1;
        $secret = $this->adminAuth->peekEnrollSecret();
        $provisioningUri = null;
        $qrDataUri = null;

        if (!$enabled && $secret !== null) {
            $provisioningUri = Totp::provisioningUri(
                $secret,
                (string) ($user['login'] ?? 'admin'),
                'Mt2 CMS Admin',
            );
            $qrDataUri = Totp::qrDataUri($provisioningUri);
        }

        $recoveryCodes = $_SESSION['_admin_2fa_recovery_codes'] ?? null;
        unset($_SESSION['_admin_2fa_recovery_codes']);

        return $this->renderAdmin('panel', [
            'activeSection' => 'account-security',
            'activeGroup' => 'settings',
            'contentTemplate' => 'pages/account-security.twig',
            'adminUser' => $user,
            'title' => $this->t('admin.2fa.account_title'),
            'pageLead' => $this->t('admin.2fa.account_lead'),
            'totpEnabled' => $enabled,
            'enrollSecret' => $secret,
            'provisioningUri' => $provisioningUri,
            'qrDataUri' => $qrDataUri,
            'twoFactorRequired' => $this->settings->adminTwoFactorRequired(),
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function startEnroll(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/account/security');
        }

        $user = $this->adminAuth->user();

        if ($user !== null && (int) ($user['totp_enabled'] ?? 0) === 1) {
            return $this->redirect('/admin/account/security');
        }

        $this->adminAuth->setEnrollSecret(Totp::generateSecret());
        unset($_SESSION['_admin_2fa_recovery_codes']);

        return $this->redirect('/admin/account/security');
    }

    public function confirmEnroll(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/account/security');
        }

        $adminId = (int) ($this->adminAuth->id() ?? 0);
        $secret = $this->adminAuth->pullEnrollSecret();
        $code = trim((string) ($_POST['totp_code'] ?? ''));

        if ($adminId < 1 || $secret === null) {
            $this->flash('error', $this->t('admin.2fa.enroll_expired'));

            return $this->redirect('/admin/account/security');
        }

        if (!Totp::verify($secret, $code)) {
            $this->adminAuth->setEnrollSecret($secret);
            $this->flash('error', $this->t('admin.2fa.invalid_code'));

            return $this->redirect('/admin/account/security');
        }

        try {
            $recoveryCodes = $this->totp->enable($adminId, $secret);
        } catch (\Throwable $e) {
            $this->adminAuth->setEnrollSecret($secret);

            throw $e;
        }

        $_SESSION['_admin_2fa_recovery_codes'] = $recoveryCodes;
        $this->audit('admin.2fa.enable', 'admin', $adminId);
        $this->flash('success', $this->t('admin.2fa.enabled'));

        return $this->redirect('/admin/account/security');
    }
}
