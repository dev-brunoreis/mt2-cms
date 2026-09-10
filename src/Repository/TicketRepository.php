<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class TicketRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/content/tickets', 'admin.tickets')
            ->defaultSort('updated_at')
            ->orderBy([
                'id' => 't.id',
                'subject' => 't.subject',
                'account_login' => 't.account_login',
                'status' => 't.status',
                'updated_at' => 't.updated_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.tickets.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'subject', 'label' => 'admin.tickets.subject', 'sort' => 'subject', 'type' => 'link', 'href' => '/admin/content/tickets/{id}'],
                ['key' => 'account_login', 'label' => 'admin.tickets.account', 'sort' => 'account_login', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.tickets.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'open' => ['class' => 'admin-badge-warn', 'label' => 'admin.tickets.status_open'],
                    'answered' => ['class' => 'admin-badge-ok', 'label' => 'admin.tickets.status_answered'],
                    'closed' => ['class' => 'admin-badge-muted', 'label' => 'admin.tickets.status_closed'],
                ]],
                ['key' => 'updated_at', 'label' => 'admin.tickets.updated', 'sort' => 'updated_at', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.tickets.status', 'type' => 'select', 'options' => [
                    'open' => 'admin.tickets.status_open',
                    'answered' => 'admin.tickets.status_answered',
                    'closed' => 'admin.tickets.status_closed',
                ]],
            ])
            ->massActions('/admin/content/tickets/mass', [
                ['id' => 'close', 'label' => 'admin.grid.close', 'confirm' => 'admin.tickets.confirm_mass_close'],
            ]);
    }

    protected function database(): string
    {
        return 'cms';
    }

    public function countForAccount(int $accountId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM tickets WHERE account_id = ?',
            [$accountId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAccount(int $accountId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->db()->fetchAll(
            'SELECT id, account_id, account_login, subject, status, created_at, updated_at
             FROM tickets
             WHERE account_id = ?
             ORDER BY updated_at DESC, id DESC
             LIMIT ? OFFSET ?',
            [$accountId, $perPage, $offset],
        );
    }

    public function countOpen(): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM tickets WHERE status = ?',
            ['open'],
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM tickets t' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), "CASE t.status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END, t.updated_at DESC, t.id DESC");

        return $this->db()->fetchAll(
            'SELECT t.id, t.account_id, t.account_login, t.subject, t.status, t.created_at, t.updated_at,
                    lm.author_type AS last_author_type,
                    lm.author_login AS last_author_login
             FROM tickets t
             LEFT JOIN ticket_messages lm ON lm.id = (
                SELECT m.id
                FROM ticket_messages m
                WHERE m.ticket_id = t.id
                ORDER BY m.id DESC
                LIMIT 1
             )' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, subject, status, created_at, updated_at
             FROM tickets
             WHERE id = ?
             LIMIT 1',
            [$id],
        );
    }

    public function findForAccount(int $id, int $accountId): ?array
    {
        return $this->db()->fetch(
            'SELECT id, account_id, account_login, subject, status, created_at, updated_at
             FROM tickets
             WHERE id = ? AND account_id = ?
             LIMIT 1',
            [$id, $accountId],
        );
    }

    /**
     * @return array{ticket_id: int, message_id: int}
     */
    public function create(int $accountId, string $accountLogin, string $subject, string $body): array
    {
        $this->db()->beginTransaction();

        try {
            $this->db()->execute(
                'INSERT INTO tickets (account_id, account_login, subject, status)
                 VALUES (?, ?, ?, ?)',
                [$accountId, $accountLogin, $subject, 'open'],
            );
            $ticketId = (int) $this->db()->lastInsertId();
            $messageId = $this->addMessage($ticketId, 'user', $accountId, $accountLogin, $body);
            $this->db()->commit();

            return [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
            ];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    public function addMessage(
        int $ticketId,
        string $authorType,
        int $authorId,
        string $authorLogin,
        string $body,
    ): int {
        $this->db()->execute(
            'INSERT INTO ticket_messages (ticket_id, author_type, author_id, author_login, body)
             VALUES (?, ?, ?, ?, ?)',
            [$ticketId, $authorType, $authorId, $authorLogin, $body],
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array{stored_name: string, original_name: string, mime: string, size_bytes: int} $file
     */
    public function addAttachment(int $ticketId, int $messageId, array $file): int
    {
        $this->db()->execute(
            'INSERT INTO ticket_attachments
                (ticket_id, message_id, stored_name, original_name, mime, size_bytes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $ticketId,
                $messageId,
                $file['stored_name'],
                $file['original_name'],
                $file['mime'],
                $file['size_bytes'],
            ],
        );

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attachmentsForTicket(int $ticketId): array
    {
        return $this->db()->fetchAll(
            'SELECT id, ticket_id, message_id, stored_name, original_name, mime, size_bytes, created_at
             FROM ticket_attachments
             WHERE ticket_id = ?
             ORDER BY id ASC',
            [$ticketId],
        );
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function attachmentsGroupedByMessage(int $ticketId): array
    {
        $grouped = [];

        foreach ($this->attachmentsForTicket($ticketId) as $row) {
            $messageId = (int) $row['message_id'];
            $grouped[$messageId] ??= [];
            $grouped[$messageId][] = $row;
        }

        return $grouped;
    }

    public function findAttachment(int $ticketId, int $attachmentId): ?array
    {
        return $this->db()->fetch(
            'SELECT id, ticket_id, message_id, stored_name, original_name, mime, size_bytes, created_at
             FROM ticket_attachments
             WHERE id = ? AND ticket_id = ?
             LIMIT 1',
            [$attachmentId, $ticketId],
        );
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->db()->execute(
            'UPDATE tickets SET status = ? WHERE id = ?',
            [$status, $id],
        ) > 0;
    }

    public function touch(int $id): void
    {
        $this->db()->execute(
            'UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$id],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $ticketId): array
    {
        return $this->db()->fetchAll(
            'SELECT id, ticket_id, author_type, author_id, author_login, body, created_at
             FROM ticket_messages
             WHERE ticket_id = ?
             ORDER BY created_at ASC, id ASC',
            [$ticketId],
        );
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];

        $status = $query->filter('status');

        if ($status !== '' && in_array($status, ['open', 'answered', 'closed'], true)) {
            $clauses[] = 't.status = ?';
            $params[] = $status;
        }

        if ($query->q !== null && $query->q !== '') {
            $clauses[] = '(t.subject LIKE ? OR t.account_login LIKE ?)';
            $params[] = '%' . $query->q . '%';
            $params[] = '%' . $query->q . '%';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }
}
