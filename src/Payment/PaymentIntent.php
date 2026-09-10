<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

final class PaymentIntent
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $accountId,
        public readonly string $accountLogin,
        public readonly int $packageId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly int $cashAmount,
        public readonly string $returnUrl,
        public readonly string $cancelUrl,
    ) {
    }
}
