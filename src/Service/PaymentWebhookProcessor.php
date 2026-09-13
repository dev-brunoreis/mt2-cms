<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PaymentWebhookEnvelope;
use Mt2Cms\Payment\WebhookEvent;
use Mt2Cms\Repository\PaymentEventRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Support\Log;

class PaymentWebhookProcessor
{
    private const LOCK_FILE = 'var/payments-process.lock';
    private const MAX_ATTEMPTS = 10;
    private const BACKOFF_SECONDS = [60, 120, 300, 900];

    public function __construct(
        private PaymentEventRepository $events,
        private PaymentRepository $payments,
        private GatewayRegistry $gateways,
        private CashCreditService $credits,
        private string $baseDir = '',
    ) {
        if ($this->baseDir === '') {
            $this->baseDir = defined('BASE_DIR') ? (string) BASE_DIR : dirname(__DIR__, 2);
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array{row: array<string, mixed>, accepted: bool, created: bool}
     */
    public function ingest(string $provider, string $rawBody, array $headers): array
    {
        if (strlen($rawBody) > PaymentWebhookEnvelope::MAX_BODY_BYTES) {
            throw new \InvalidArgumentException('payments.webhook_too_large');
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('payments.invalid_webhook');
        }

        $eventId = PaymentWebhookEnvelope::eventId($provider, $payload, $rawBody);
        $eventType = PaymentWebhookEnvelope::eventType($provider, $payload);
        $stored = $this->events->insertOrGet([
            'provider' => $provider,
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'headers_json' => json_encode(
                PaymentWebhookEnvelope::filterHeaders($headers),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ),
            'raw_body' => $rawBody,
        ]);
        $row = $stored['row'];
        $id = (int) $row['id'];

        if ((string) $row['status'] === 'processed' || (string) $row['status'] === 'processing') {
            return ['row' => $row, 'accepted' => true, 'created' => false];
        }

        try {
            $parsed = $this->gateways->get($provider)->parseWebhook($rawBody, $headers);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'payments.invalid_webhook_signature') {
                $this->events->markRejected($id, $e->getMessage());

                return ['row' => $this->events->findById($id) ?? $row, 'accepted' => false, 'created' => $stored['created']];
            }

            $this->events->markRejected($id, $e->getMessage());

            return ['row' => $this->events->findById($id) ?? $row, 'accepted' => false, 'created' => $stored['created']];
        }

        if (in_array((string) $row['status'], ['rejected', 'failed'], true)) {
            $this->events->markQueued($id);
        }

        $this->attachIfPossible($id, $provider, $payload, $parsed);

        return [
            'row' => $this->events->findById($id) ?? $row,
            'accepted' => true,
            'created' => $stored['created'],
        ];
    }

    /**
     * @return array{skipped: bool, processed: int, failed: int, retried: int}
     */
    public function run(int $limit = 10): array
    {
        $result = ['skipped' => false, 'processed' => 0, 'failed' => 0, 'retried' => 0];
        $lock = $this->acquireLock();

        if ($lock === null) {
            $result['skipped'] = true;

            return $result;
        }

        try {
            foreach ($this->events->claimDue($limit) as $row) {
                $outcome = $this->processClaimed($row);

                if ($outcome === 'processed') {
                    $result['processed']++;
                } elseif ($outcome === 'retried') {
                    $result['retried']++;
                } else {
                    $result['failed']++;
                }
            }
        } finally {
            $this->releaseLock($lock);
        }

        return $result;
    }

    public function processOne(int $id): bool
    {
        $row = $this->events->findById($id);

        if ($row === null) {
            return false;
        }

        $status = (string) $row['status'];

        if ($status === 'processed') {
            return true;
        }

        if ($status === 'rejected') {
            return false;
        }

        return $this->processClaimed($row) === 'processed';
    }

    public function retry(int $eventId, int $paymentId): bool
    {
        $row = $this->events->findById($eventId);

        if ($row === null || (int) ($row['payment_id'] ?? 0) !== $paymentId) {
            return false;
        }

        return $this->events->requeue($eventId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function processClaimed(array $row): string
    {
        $id = (int) $row['id'];
        $provider = (string) $row['provider'];
        $raw = (string) $row['raw_body'];

        try {
            if (!$this->gateways->has($provider)) {
                throw new \RuntimeException('payments.unknown_gateway');
            }

            $gateway = $this->gateways->get($provider);
            $payload = json_decode($raw, true);
            $payload = is_array($payload) ? $payload : [];
            $hints = PaymentWebhookEnvelope::paymentHints($provider, $payload);
            $parsed = new WebhookEvent(
                $hints['provider_ref'] !== '' ? $hints['provider_ref'] : (string) $row['provider_event_id'],
                (string) $row['event_type'],
                false,
            );
            $event = $gateway->processWebhook($parsed);

            if ($event->paid) {
                $this->credits->markPaidAndCredit($provider, $event->providerRef);
            }

            $this->attachIfPossible($id, $provider, $payload, $event);
            $this->events->markProcessed($id);

            return 'processed';
        } catch (\Throwable $e) {
            Log::error('payments', $provider . ' webhook process failed', $e);
            $attempts = (int) $row['attempts'] + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->events->markRetry($id, $attempts, date('Y-m-d H:i:s'), $e->getMessage());

                return 'failed';
            }

            $delay = self::BACKOFF_SECONDS[min($attempts, count(self::BACKOFF_SECONDS)) - 1];
            $this->events->markRetry(
                $id,
                $attempts,
                date('Y-m-d H:i:s', time() + $delay),
                $e->getMessage(),
            );

            return 'retried';
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function attachIfPossible(int $eventId, string $provider, array $payload, WebhookEvent $event): void
    {
        $hints = PaymentWebhookEnvelope::paymentHints($provider, $payload);
        $paymentId = $hints['payment_id'];

        if ($paymentId > 0 && $this->payments->findById($paymentId) !== null) {
            $this->events->attachPayment($eventId, $paymentId);

            return;
        }

        $refs = array_filter([
            $event->providerRef,
            $hints['provider_ref'],
        ], static fn (string $ref): bool => $ref !== '' && !str_starts_with($ref, 'tmp-'));

        foreach ($refs as $ref) {
            $payment = $this->payments->findByProviderRef($provider, $ref);

            if ($payment !== null) {
                $this->events->attachPayment($eventId, (int) $payment['id']);

                return;
            }
        }
    }

    /** @return resource|null */
    private function acquireLock()
    {
        $path = rtrim($this->baseDir, '/') . '/' . self::LOCK_FILE;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $fh = @fopen($path, 'c+');

        if ($fh === false) {
            return null;
        }

        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return null;
        }

        ftruncate($fh, 0);
        fwrite($fh, (string) getmypid());

        return $fh;
    }

    /** @param resource $lock */
    private function releaseLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
