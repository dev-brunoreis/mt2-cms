# Payments

Donate cash packages via pluggable gateways (PayPal today). Admin list: `/admin/store/payments`. Settings: `/admin/settings?tab=payment-methods`. ACL: `store/payments/{view,edit}`, `store/packages/*`, `settings/payment-methods/{view,edit}`.

## Flow

1. Player `POST /donate/buy` (CSRF, auth, rate limit) → `PaymentCheckoutService::startCheckout`.
2. Pending row in `cms_payments`; gateway `createCheckout()` returns an approval URL.
3. Browser goes to `/donate/pay` then to the provider (`form-action 'self'` blocks a 302 straight to PayPal).
4. Provider hits `POST /payments/webhook/{provider}` — **no CSRF**. `PaymentWebhookProcessor::ingest` verifies signature **fail-closed**, stores the raw body in `cms_payment_events`, does not credit inline.
5. `php bin/payments-process.php` (lock `var/payments-process.lock`) captures/credits with backoff. Also expires due pendings.
6. Return/cancel URLs only show status; they are not the credit path.

Invalid signatures are not attached to a payment. Raw bodies are visible only on the admin payment detail (`store/payments/view`).

## Credit

`CashCreditService` adds coins on the game account and is the only credit path. Admin `recredit` / event `retry` need `store/payments/edit` + audit.

On terminal states, `NotificationService` pushes in-app notices (`payment_credited`, `payment_failed`, `payment_expired`, `payment_cancelled`).

## Adding a gateway

Implement `Mt2Cms\Payment\PaymentGateway`, register in `GatewayRegistry` (wired in `Application` / bootstrap). Do not fork `DonateController`. Toggle + webhook id live in **Settings → Payment methods**.
