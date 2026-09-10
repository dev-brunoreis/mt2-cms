<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

final class CheckoutRedirect
{
    public function __construct(
        public readonly string $providerRef,
        public readonly string $approvalUrl,
    ) {
    }
}
