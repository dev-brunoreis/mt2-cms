<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PaymentGateway;
use Mt2Cms\Payment\PaymentIntent;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\PaymentRepository;

class PaymentCheckoutService
{
    public function __construct(
        private CashPackageRepository $packages,
        private PaymentRepository $payments,
        private GatewayRegistry $gateways,
        private SettingsService $settings,
        private NotificationService $notifications,
        private PaymentExpiryService $expiry,
    ) {
    }

    /**
     * @return array{approval_url: string, payment_id: int}
     */
    public function startCheckout(int $accountId, string $accountLogin, int $packageId): array
    {
        $this->expiry->expireDue();

        $gateway = $this->gateways->active();

        if ($gateway === null) {
            throw new \RuntimeException('donate.paypal_unavailable');
        }

        $package = $this->packages->findEnabledById($packageId);

        if ($package === null) {
            throw new \RuntimeException('donate.package_unavailable');
        }

        $currency = (string) ($package['currency'] ?? $gateway->currency());
        $tempRef = 'tmp-' . bin2hex(random_bytes(16));
        $payment = $this->payments->createPending([
            'account_id' => $accountId,
            'account_login' => $accountLogin,
            'package_id' => $packageId,
            'provider' => $gateway->id(),
            'provider_ref' => $tempRef,
            'amount_cents' => (int) $package['price_cents'],
            'currency' => $currency,
            'cash_amount' => (int) $package['cash_amount'],
        ]);

        $paymentId = (int) $payment['id'];
        $cashAmount = (int) $package['cash_amount'];
        $base = rtrim($this->settings->siteUrl(), '/');
        $intent = new PaymentIntent(
            $paymentId,
            $accountId,
            $accountLogin,
            $packageId,
            (int) $package['price_cents'],
            $currency,
            $cashAmount,
            $base . '/donate/return?payment_id=' . $paymentId,
            $base . '/donate/cancel?payment_id=' . $paymentId,
        );

        try {
            $redirect = $gateway->createCheckout($intent);
            $this->payments->updateProviderRef($paymentId, $redirect->providerRef);
        } catch (\Throwable $e) {
            if ($this->payments->markFailed($paymentId)) {
                $this->notifications->paymentFailed($accountId, $cashAmount, $paymentId);
            }

            throw $e;
        }

        return ['approval_url' => $redirect->approvalUrl, 'payment_id' => $paymentId];
    }

    public function cancelPending(int $paymentId, int $accountId): bool
    {
        $payment = $this->payments->findByIdForAccount($paymentId, $accountId);

        if ($payment === null || (string) ($payment['status'] ?? '') !== 'pending') {
            return false;
        }

        if (!$this->payments->markFailed($paymentId)) {
            return false;
        }

        $full = $this->payments->findById($paymentId);
        $cashAmount = (int) ($full['cash_amount'] ?? 0);
        $this->notifications->paymentCancelled($accountId, $cashAmount, $paymentId);

        return true;
    }
}
