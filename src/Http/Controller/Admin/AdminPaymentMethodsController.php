<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminPaymentMethodsController extends AdminController
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
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        return $this->redirect(AdminPaths::settingsPaymentMethods());
    }

    public function save(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/payment-methods/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsPaymentMethods());
        }

        try {
            $this->savePaypal();
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));

            return $this->redirect(AdminPaths::settingsPaymentMethods());
        }

        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::settingsPaymentMethods());
    }

    private function savePaypal(): void
    {
        $secretPosted = SettingsService::postedSecret((string) ($_POST['paypal_client_secret'] ?? ''));
        $before = $this->paypalAuditSnapshot();

        $this->settings->setPaypalEnabled(isset($_POST['paypal_enabled']));
        $this->settings->setPaypalMode(trim((string) ($_POST['paypal_mode'] ?? 'sandbox')));
        $this->settings->setPaypalCurrency(trim((string) ($_POST['paypal_currency'] ?? 'USD')));
        $this->settings->setPaypalClientId(trim((string) ($_POST['paypal_client_id'] ?? '')));
        if ($secretPosted !== null) {
            $this->settings->setPaypalClientSecret($secretPosted);
        }
        $this->settings->setPaypalWebhookId(trim((string) ($_POST['paypal_webhook_id'] ?? '')));
        $this->settings->setPaypalPendingMinutes((int) ($_POST['paypal_pending_minutes'] ?? 30));

        $this->auditChange(
            'settings.payment_methods_save',
            'settings',
            null,
            $before,
            $this->paypalAuditSnapshot($secretPosted !== null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paypalAuditSnapshot(bool $secretUpdated = false): array
    {
        $snapshot = [
            'gateway' => 'paypal',
            'paypal_enabled' => $this->settings->paypalEnabled(),
            'paypal_mode' => $this->settings->paypalMode(),
            'paypal_currency' => $this->settings->paypalCurrency(),
            'paypal_client_id' => $this->settings->paypalClientId(),
            'paypal_webhook_id' => $this->settings->paypalWebhookId(),
            'paypal_pending_minutes' => $this->settings->paypalPendingMinutes(),
            'paypal_configured' => $this->settings->paypalConfigured(),
        ];

        if ($secretUpdated) {
            $snapshot['paypal_secret_updated'] = true;
        }

        return $snapshot;
    }
}
