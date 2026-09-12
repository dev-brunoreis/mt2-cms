<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\PaymentRepository;

class PaymentExpiryService
{
    public function __construct(
        private PaymentRepository $payments,
        private SettingsService $settings,
        private NotificationService $notifications,
    ) {
    }

    public function expireDue(): int
    {
        $minutes = $this->settings->paypalPendingMinutes();
        $rows = $this->payments->listExpiredPending($minutes);
        $count = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $accountId = (int) ($row['account_id'] ?? 0);
            $cashAmount = (int) ($row['cash_amount'] ?? 0);

            if ($id < 1 || !$this->payments->markExpired($id)) {
                continue;
            }

            $this->notifications->paymentExpired($accountId, $cashAmount, $id);
            $count++;
        }

        return $count;
    }
}
