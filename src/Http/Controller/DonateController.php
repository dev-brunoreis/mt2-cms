<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\Support\Log;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Payment\CheckoutUrl;
use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class DonateController extends Controller
{
    private const CHECKOUT_SESSION_KEY = '_donate_checkout_url';

    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private CashPackageRepository $packages,
        private PaymentRepository $payments,
        private PaymentCheckoutService $checkout,
        private CashCreditService $credits,
        private GatewayRegistry $gateways,
        private AccountEmailService $accountEmails,
        private SettingsService $settings,
        ?RateLimiter $rateLimiter = null,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter(5, 900);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($block = $this->requireVerifiedIfNeeded()) {
            return $block;
        }

        $gateways = $this->gateways->available();
        $active = $gateways[0] ?? null;

        return $this->view('donate', [
            'title' => $this->t('donate.title'),
            'packages' => $this->packages->listEnabled(),
            'currency' => $active?->currency() ?? $this->settings->paypalCurrency(),
            'paymentsConfigured' => $gateways !== [],
            'paymentGateways' => array_map(
                static fn ($g): array => [
                    'id' => $g->id(),
                    'label_key' => $g->labelKey(),
                ],
                $gateways,
            ),
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

        $gatewayId = trim((string) ($_POST['gateway'] ?? ''));

        if ($this->gateways->resolve($gatewayId !== '' ? $gatewayId : null) === null) {
            $this->flash('error', $this->t('donate.payments_unavailable'));

            return $this->redirect('/donate');
        }

        $packageId = (int) ($_POST['package_id'] ?? 0);
        $accountId = $this->auth->id();
        $login = $this->auth->login();

        if ($accountId === null || $login === null) {
            return $this->redirect('/login');
        }

        $bucket = 'donate-buy:' . Request::clientIp() . ':' . $accountId;

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            $this->flash('error', $this->t('auth.too_many_attempts'));

            return $this->redirect('/donate');
        }

        $this->rateLimiter->hit($bucket);

        try {
            $result = $this->checkout->startCheckout(
                $accountId,
                $login,
                $packageId,
                $gatewayId !== '' ? $gatewayId : null,
            );
        } catch (\Throwable) {
            $this->flash('error', $this->t('donate.checkout_failed'));

            return $this->redirect('/donate');
        }

        $url = $result['approval_url'];

        if (!CheckoutUrl::isSafe($url)) {
            $this->flash('error', $this->t('donate.checkout_failed'));

            return $this->redirect('/donate');
        }

        $_SESSION[self::CHECKOUT_SESSION_KEY] = $url;

        return $this->redirect('/donate/pay');
    }

    public function pay(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $url = (string) ($_SESSION[self::CHECKOUT_SESSION_KEY] ?? '');

        if (!CheckoutUrl::isSafe($url)) {
            $this->flash('error', $this->t('donate.checkout_failed'));

            return $this->redirect('/donate');
        }

        return $this->view('donate-pay', [
            'title' => $this->t('donate.redirect_title'),
            'checkout_url' => $url,
        ]);
    }

    public function returnUrl(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $paymentId = (int) ($_GET['payment_id'] ?? 0);
        $accountId = $this->auth->id();

        if ($accountId === null || $paymentId < 1) {
            return $this->redirect('/donate');
        }

        $payment = $this->payments->findByIdForAccount($paymentId, $accountId);

        if ($payment === null) {
            $this->flash('error', $this->t('donate.payment_not_found'));

            return $this->redirect('/donate');
        }

        $provider = (string) ($payment['provider'] ?? '');
        $query = [];

        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }

        if ($provider !== '' && $this->gateways->has($provider)) {
            try {
                $event = $this->gateways->get($provider)->captureReturn($query);

                if ($event !== null && $event->paid) {
                    $this->credits->markPaidAndCredit($provider, $event->providerRef);
                } elseif ($event === null) {
                    $providerRef = (string) ($payment['provider_ref'] ?? '');

                    if ($providerRef !== '' && !str_starts_with($providerRef, 'tmp-')) {
                        $this->credits->markPaidAndCredit($provider, $providerRef);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('payments', 'Donate return capture/credit failed', $e);
            }
        }

        $this->flash('success', $this->t('donate.return_pending'));

        return $this->redirect('/account/payments');
    }

    public function cancel(): Response
    {
        $paymentId = (int) ($_GET['payment_id'] ?? 0);
        $accountId = $this->auth->id();

        if ($accountId !== null && $paymentId > 0) {
            $this->checkout->cancelPending($paymentId, $accountId);
        }

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
