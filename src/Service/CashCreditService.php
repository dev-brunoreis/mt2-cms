<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Service\DiscordWebhookService;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Support\Log;

class CashCreditService
{
    public function __construct(
        private PaymentRepository $payments,
        private AccountRepository $accounts,
        private DiscordWebhookService $discord,
    ) {
    }

    public function creditIfPaid(string $provider, string $providerRef): bool
    {
        $payment = $this->payments->findByProviderRef($provider, $providerRef);

        if ($payment === null) {
            return false;
        }

        if ($payment['credited_at'] !== null) {
            return true;
        }

        $paymentId = (int) $payment['id'];
        $accountId = (int) $payment['account_id'];
        $cashAmount = (int) $payment['cash_amount'];
        $lockName = 'payment:credit:' . $provider . ':' . $providerRef;

        if (!$this->accounts->acquireNamedLock($lockName)) {
            throw new \RuntimeException('payments.lock_failed');
        }

        try {
            $payment = $this->payments->findByProviderRef($provider, $providerRef);

            if ($payment === null || $payment['credited_at'] !== null) {
                return $payment !== null;
            }

            if ((string) ($payment['status'] ?? '') !== 'paid') {
                $this->payments->markPaid($paymentId);
            }

            if (!$this->accounts->creditCash($accountId, $cashAmount)) {
                Log::error('payments', 'Cash credit failed for account ' . $accountId);

                return false;
            }

            $this->payments->markCredited($paymentId);
            $this->discord->notifyPaymentCredited(
                (string) ($payment['account_login'] ?? ''),
                $cashAmount,
            );

            return true;
        } finally {
            $this->accounts->releaseNamedLock($lockName);
        }
    }

    public function markPaidAndCredit(string $provider, string $providerRef): bool
    {
        $payment = $this->payments->findByProviderRef($provider, $providerRef);

        if ($payment === null) {
            return false;
        }

        if ((string) ($payment['status'] ?? '') === 'pending') {
            $this->payments->markPaid((int) $payment['id']);
        }

        return $this->creditIfPaid($provider, $providerRef);
    }
}
