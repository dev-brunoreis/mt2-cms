<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Payment\PayPalGateway;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Support\Log;
use Mt2Cms\Theme\ThemeEngine;

class PaymentWebhookController extends Controller
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private PayPalGateway $paypal,
        private CashCreditService $credits,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function paypal(): Response
    {
        $raw = file_get_contents('php://input');

        if (!is_string($raw) || $raw === '') {
            return Response::html('Bad Request', 400);
        }

        /** @var array<string, string> $headers */
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        try {
            $event = $this->paypal->parseWebhook($raw, $headers);

            if ($event->paid) {
                $this->credits->markPaidAndCredit('paypal', $event->providerRef);
            }
        } catch (\Throwable $e) {
            Log::error('payments', 'PayPal webhook failed', $e);

            return Response::html('Bad Request', 400);
        }

        return Response::html('OK', 200);
    }
}
