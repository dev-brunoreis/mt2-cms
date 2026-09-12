<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use PDOException;

class NotificationRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    public function create(int $accountId, string $type, string $ref, array $payload): bool
    {
        if ($accountId < 1 || $type === '' || $ref === '') {
            return false;
        }

        try {
            $this->db()->execute(
                'INSERT INTO cms_notifications (account_id, type, ref, payload)
                 VALUES (?, ?, ?, ?)',
                [
                    $accountId,
                    $type,
                    mb_substr($ref, 0, 64),
                    json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            );

            return true;
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByAccountId(int $accountId, int $limit = 50): array
    {
        if ($accountId < 1) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            'SELECT id, type, ref, payload, read_at, created_at
             FROM cms_notifications
             WHERE account_id = ?
             ORDER BY id DESC
             LIMIT ?',
            [$accountId, max(1, min(100, $limit))],
        );

        return array_map(function (array $row): array {
            $decoded = json_decode((string) ($row['payload'] ?? ''), true);

            return [
                'id' => (int) $row['id'],
                'type' => (string) $row['type'],
                'ref' => (string) $row['ref'],
                'payload' => is_array($decoded) ? $this->scalarPayload($decoded) : [],
                'read_at' => $row['read_at'] ?? null,
                'unread' => ($row['read_at'] ?? null) === null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);
    }

    public function countUnread(int $accountId): int
    {
        if ($accountId < 1) {
            return 0;
        }

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM cms_notifications WHERE account_id = ? AND read_at IS NULL',
            [$accountId],
        );
    }

    public function markRead(int $id, int $accountId): bool
    {
        if ($id < 1 || $accountId < 1) {
            return false;
        }

        return $this->db()->execute(
            'UPDATE cms_notifications SET read_at = NOW()
             WHERE id = ? AND account_id = ? AND read_at IS NULL',
            [$id, $accountId],
        ) > 0;
    }

    public function markAllRead(int $accountId): int
    {
        if ($accountId < 1) {
            return 0;
        }

        return $this->db()->execute(
            'UPDATE cms_notifications SET read_at = NOW()
             WHERE account_id = ? AND read_at IS NULL',
            [$accountId],
        );
    }

    /**
     * @param array<mixed> $payload
     * @return array<string, string>
     */
    private function scalarPayload(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_bool($value)) {
                $out[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_int($value) || is_float($value) || is_string($value)) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;

        return (int) $code === 1062 || str_contains($e->getMessage(), 'Duplicate');
    }
}
