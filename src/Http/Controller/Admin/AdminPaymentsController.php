<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\PaymentsGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PaymentEventRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\PaymentExpiryService;
use Mt2Cms\Service\PaymentWebhookProcessor;
use Mt2Cms\Theme\ThemeEngine;

class AdminPaymentsController extends AdminController
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
        private PaymentRepository $payments,
        private PaymentEventRepository $paymentEvents,
        private PaymentWebhookProcessor $webhookProcessor,
        private CashCreditService $credits,
        private PaymentExpiryService $expiry,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $this->expiry->expireDue();

        $spec = PaymentsGrid::definition()->spec();
        $grid = GridRunner::fetch(
            $spec,
            $this->gridQuery($spec),
            fn ($q) => $this->payments->countForGrid($q),
            fn ($q) => $this->payments->listForGrid($q),
        );

        return $this->adminView('payments', 'pages/payments.twig', [
            'title' => $this->t('admin.payments.title'),
            'pageLead' => $this->t('admin.payments.lead'),
            'grid' => $grid,
        ]);
    }

    public function show(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/payments/view')) {
            return $redirect;
        }

        $paymentId = (int) $id;
        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            $this->flash('error', $this->t('admin.payments.not_found'));

            return $this->redirect('/admin/store/payments');
        }

        $canEdit = $this->acl->isAllowed($this->adminAuth->user(), 'store/payments/edit');

        return $this->adminView('payments', 'pages/payment-detail.twig', [
            'title' => $this->t('admin.payments.detail_title', ['id' => $paymentId]),
            'pageLead' => $this->t('admin.payments.detail_lead'),
            'payment' => $payment,
            'webhookEvents' => $this->presentEvents($this->paymentEvents->listByPaymentId($paymentId)),
            'canRecredit' => $canEdit && $payment['credited_at'] === null,
            'canRetryWebhook' => $canEdit,
        ]);
    }

    public function retryEvent(string $id, string $eventId): Response
    {
        if ($redirect = $this->requireAdminResource('store/payments/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/store/payments/' . (int) $id);
        }

        $paymentId = (int) $id;

        if ($this->webhookProcessor->retry((int) $eventId, $paymentId)) {
            $this->audit('payment.webhook_retry', 'payment', $paymentId);
            $this->webhookProcessor->processOne((int) $eventId);
            $this->flash('success', $this->t('admin.payments.webhook_retried'));
        } else {
            $this->flash('error', $this->t('admin.payments.webhook_retry_failed'));
        }

        return $this->redirect('/admin/store/payments/' . $paymentId);
    }

    public function recredit(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/payments/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/store/payments/' . (int) $id);
        }

        $paymentId = (int) $id;
        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            $this->flash('error', $this->t('admin.payments.not_found'));

            return $this->redirect('/admin/store/payments');
        }

        if ($payment['credited_at'] !== null) {
            $this->flash('error', $this->t('admin.payments.already_credited'));

            return $this->redirect('/admin/store/payments/' . $paymentId);
        }

        $provider = (string) ($payment['provider'] ?? '');
        $ref = (string) ($payment['provider_ref'] ?? '');

        if ($this->credits->markPaidAndCredit($provider, $ref)) {
            $this->audit('payment.recredit', 'payment', $paymentId);
            $this->flash('success', $this->t('admin.payments.recredited'));
        } else {
            $this->flash('error', $this->t('admin.payments.recredit_failed'));
        }

        return $this->redirect('/admin/store/payments/' . $paymentId);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentEvents(array $rows): array
    {
        $presented = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['raw_body'] ?? ''), true);
            $headers = json_decode((string) ($row['headers_json'] ?? ''), true);
            $row['raw_pretty'] = is_array($decoded)
                ? (string) json_encode(
                    $decoded,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )
                : (string) ($row['raw_body'] ?? '');
            $row['headers'] = is_array($headers) ? $headers : [];
            $presented[] = $row;
        }

        return $presented;
    }
}
