<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Payment\PayPalGateway;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class DonateController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private CashPackageRepository $packages,
        private PaymentRepository $payments,
        private PaymentCheckoutService $checkout,
        private CashCreditService $credits,
        private PayPalGateway $paypal,
        private AccountEmailService $accountEmails,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($block = $this->requireVerifiedIfNeeded()) {
            return $block;
        }

        return $this->view('donate', [
            'title' => $this->t('donate.title'),
            'packages' => $this->packages->listEnabled(),
            'currency' => $this->settings->paypalCurrency(),
        ]);
    }

    public function buy(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($block = $this->requireVerifiedIfNeeded()) {
            return $block;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/donate');
        }

        $packageId = (int) ($_POST['package_id'] ?? 0);
        $accountId = $this->auth->id();
        $login = $this->auth->login();

        if ($accountId === null || $login === null) {
            return $this->redirect('/login');
        }

        try {
            $result = $this->checkout->startCheckout($accountId, $login, $packageId);

            return Response::redirect($result['approval_url']);
        } catch (\Throwable) {
            $this->flash('error', $this->t('donate.checkout_failed'));

            return $this->redirect('/donate');
        }
    }

    public function returnUrl(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $paymentId = (int) ($_GET['payment_id'] ?? 0);
        $token = trim((string) ($_GET['token'] ?? ''));
        $accountId = $this->auth->id();

        if ($accountId === null || $paymentId < 1) {
            return $this->redirect('/donate');
        }

        $payment = $this->payments->findByIdForAccount($paymentId, $accountId);

        if ($payment === null) {
            $this->flash('error', $this->t('donate.payment_not_found'));

            return $this->redirect('/donate');
        }

        $providerRef = $token !== '' ? $token : (string) ($payment['provider_ref'] ?? '');

        if ($providerRef !== '') {
            try {
                $this->paypal->captureOrder($providerRef);
                $this->credits->markPaidAndCredit('paypal', $providerRef);
            } catch (\Throwable) {
                // Webhook may still credit later.
            }
        }

        $this->flash('success', $this->t('donate.return_pending'));

        return $this->redirect('/account/payments');
    }

    public function cancel(): Response
    {
        $this->flash('error', $this->t('donate.cancelled'));

        return $this->redirect('/donate');
    }

    private function requireVerifiedIfNeeded(): ?Response
    {
        if (!$this->settings->requireVerifiedEmail()) {
            return null;
        }

        $accountId = $this->auth->id();

        if ($accountId !== null && !$this->accountEmails->isVerified($accountId)) {
            $this->flash('error', $this->t('auth.email_not_verified'));

            return $this->redirect('/account');
        }

        return null;
    }
}
