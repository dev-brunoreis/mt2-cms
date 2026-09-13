<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class PaymentEventRepository extends Repository
{
    private const SELECT = 'id, payment_id, provider, provider_event_id, event_type, headers_json,
                    raw_body, status, attempts, available_at, last_error, processed_at, created_at';

    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @param array{
     *   provider: string,
     *   provider_event_id: string,
     *   event_type: string,
     *   headers_json: string,
     *   raw_body: string
     * } $data
     * @return array{row: array<string, mixed>, created: bool}
     */
    public function insertOrGet(array $data): array
    {
        $existing = $this->findByProviderEvent($data['provider'], $data['provider_event_id']);

        if ($existing !== null) {
            return ['row' => $existing, 'created' => false];
        }

        try {
            $this->db()->execute(
                'INSERT INTO cms_payment_events
                 (payment_id, provider, provider_event_id, event_type, headers_json, raw_body, status, attempts)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    null,
                    $data['provider'],
                    $data['provider_event_id'],
                    $data['event_type'],
                    $data['headers_json'],
                    $data['raw_body'],
                    'queued',
                    0,
                ],
            );
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $row = $this->findByProviderEvent($data['provider'], $data['provider_event_id']);

            if ($row === null) {
                throw $e;
            }

            return ['row' => $row, 'created' => false];
        }

        $id = (int) $this->db()->lastInsertId();
        $row = $this->findById($id);

        if ($row === null) {
            throw new \RuntimeException('payments.event_create_failed');
        }

        return ['row' => $row, 'created' => true];
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT ' . self::SELECT . ' FROM cms_payment_events WHERE id = ?',
            [$id],
        );
    }

    public function findByProviderEvent(string $provider, string $eventId): ?array
    {
        return $this->db()->fetch(
            'SELECT ' . self::SELECT . '
             FROM cms_payment_events
             WHERE provider = ? AND provider_event_id = ?
             LIMIT 1',
            [$provider, $eventId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByPaymentId(int $paymentId): array
    {
        return $this->db()->fetchAll(
            'SELECT ' . self::SELECT . '
             FROM cms_payment_events
             WHERE payment_id = ?
             ORDER BY created_at ASC, id ASC',
            [$paymentId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimDue(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $candidates = $this->db()->fetchAll(
            'SELECT ' . self::SELECT . '
             FROM cms_payment_events
             WHERE (
                    (status IN (?, ?) AND attempts < ?)
                    OR status = ?
                 )
               AND available_at <= NOW()
             ORDER BY available_at ASC, id ASC
             LIMIT ?',
            ['queued', 'failed', 10, 'processing', $limit],
        );
        $claimed = [];

        foreach ($candidates as $row) {
            $id = (int) $row['id'];
            $updated = $this->db()->execute(
                'UPDATE cms_payment_events
                 SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                 WHERE id = ? AND available_at <= NOW() AND (
                    (status IN (?, ?) AND attempts < ?)
                    OR status = ?
                 )',
                ['processing', $id, 'queued', 'failed', 10, 'processing'],
            );

            if ($updated > 0) {
                $fresh = $this->findById($id);

                if ($fresh !== null) {
                    $claimed[] = $fresh;
                }
            }
        }

        return $claimed;
    }

    public function attachPayment(int $id, int $paymentId): void
    {
        $this->db()->execute(
            'UPDATE cms_payment_events SET payment_id = ? WHERE id = ? AND payment_id IS NULL',
            [$paymentId, $id],
        );
    }

    public function markRejected(int $id, string $error): void
    {
        $this->db()->execute(
            'UPDATE cms_payment_events
             SET status = ?, last_error = ?, processed_at = NOW()
             WHERE id = ?',
            ['rejected', $this->truncateError($error), $id],
        );
    }

    public function markProcessed(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payment_events
             SET status = ?, last_error = NULL, processed_at = NOW()
             WHERE id = ?',
            ['processed', $id],
        );
    }

    public function markRetry(int $id, int $attempts, string $availableAt, string $error): void
    {
        $this->db()->execute(
            'UPDATE cms_payment_events
             SET status = ?, attempts = ?, available_at = ?, last_error = ?
             WHERE id = ?',
            ['failed', $attempts, $availableAt, $this->truncateError($error), $id],
        );
    }

    public function markQueued(int $id): void
    {
        $this->db()->execute(
            'UPDATE cms_payment_events
             SET status = ?, last_error = NULL, processed_at = NULL, available_at = NOW()
             WHERE id = ? AND status IN (?, ?)',
            ['queued', $id, 'rejected', 'failed'],
        );
    }

    public function requeue(int $id): bool
    {
        return $this->db()->execute(
            'UPDATE cms_payment_events
             SET status = ?, attempts = 0, available_at = NOW(), last_error = NULL, processed_at = NULL
             WHERE id = ? AND status = ?',
            ['queued', $id, 'failed'],
        ) > 0;
    }

    private function truncateError(string $error): string
    {
        if (strlen($error) <= 255) {
            return $error;
        }

        return substr($error, 0, 252) . '...';
    }
}
