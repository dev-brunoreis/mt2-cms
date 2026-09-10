<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\TicketRepository;
use Mt2Cms\Service\TicketUploadService;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminTicketsController extends AdminController
{
    private const MAX_HTML_BYTES = 20000;
    private const MAX_PLAIN_CHARS = 5000;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private TicketRepository $tickets,
        private TicketUploadService $uploads,
        private HtmlSanitizer $sanitizer,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->tickets->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->tickets->countForGrid($q),
            fn ($q) => $this->enrichTickets($this->tickets->listForGrid($q)),
        );

        return $this->adminView('tickets', 'pages/tickets.twig', [
            'title' => $this->t('admin.tickets.title'),
            'pageLead' => $this->t('admin.tickets.lead'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->tickets->gridDefinition()->spec(),
            '/admin/content/tickets',
            [
                'close' => function (int $id): bool {
                    if ($this->tickets->findById($id) === null) {
                        return false;
                    }

                    return $this->tickets->setStatus($id, 'closed');
                },
            ],
            'ticket',
            'admin.tickets.mass_done',
            'content/tickets/mass',
        );
    }

    public function show(string $id): Response
    {
        $ticket = $this->tickets->findById((int) $id);

        if ($ticket === null) {
            $this->flash('error', $this->t('admin.tickets.not_found'));

            return $this->redirect('/admin/content/tickets');
        }

        return $this->adminView('tickets', 'pages/ticket-show.twig', [
            'title' => $this->t('admin.tickets.view', ['id' => (string) $ticket['id']]),
            'pageLead' => (string) $ticket['subject'],
            'ticket' => $ticket,
            'messages' => $this->tickets->messages((int) $ticket['id']),
            'attachmentsByMessage' => $this->tickets->attachmentsGroupedByMessage((int) $ticket['id']),
            'attachmentBase' => '/admin/content/tickets/' . (int) $ticket['id'] . '/attachments/',
        ]);
    }

    public function downloadAttachment(string $id, string $attachmentId): Response
    {
        if ($redirect = $this->requireAdminSection('tickets')) {
            return $redirect;
        }

        $ticket = $this->tickets->findById((int) $id);

        if ($ticket === null) {
            return Response::notFound();
        }

        $attachment = $this->tickets->findAttachment((int) $ticket['id'], (int) $attachmentId);

        if ($attachment === null) {
            return Response::notFound();
        }

        try {
            $path = $this->uploads->absolutePath((string) $attachment['stored_name']);
        } catch (\InvalidArgumentException) {
            return Response::notFound();
        }

        return Response::download(
            $path,
            (string) $attachment['original_name'],
            (string) $attachment['mime'],
        );
    }

    public function reply(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/tickets/reply')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/tickets/' . (int) $id);
        }

        $ticket = $this->tickets->findById((int) $id);

        if ($ticket === null) {
            $this->flash('error', $this->t('admin.tickets.not_found'));

            return $this->redirect('/admin/content/tickets');
        }

        if ((string) $ticket['status'] === 'closed') {
            $this->flash('error', $this->t('admin.tickets.closed'));

            return $this->redirect('/admin/content/tickets/' . (int) $id);
        }

        $body = $this->sanitizer->sanitize((string) ($_POST['body'] ?? ''), false);

        if (!$this->isValidHtmlBody($body)) {
            $this->flash('error', $this->t('admin.tickets.invalid_reply'));

            return $this->redirect('/admin/content/tickets/' . (int) $id);
        }

        $admin = $this->adminAuth->user();

        if ($admin === null) {
            return $this->redirect('/admin/login');
        }

        $this->tickets->addMessage(
            (int) $ticket['id'],
            'admin',
            (int) $admin['id'],
            (string) $admin['login'],
            $body,
        );
        $this->tickets->setStatus((int) $ticket['id'], 'answered');
        $this->tickets->touch((int) $ticket['id']);
        $this->auditChange('ticket.reply', 'ticket', (int) $ticket['id'], [
            'status' => $ticket['status'],
        ], [
            'status' => 'answered',
        ]);
        $this->flash('success', $this->t('admin.tickets.replied'));

        return $this->redirect('/admin/content/tickets/' . (int) $id);
    }

    public function close(string $id): Response
    {
        return $this->setStatus((int) $id, 'closed', 'admin.tickets.closed_ok');
    }

    public function reopen(string $id): Response
    {
        return $this->setStatus((int) $id, 'open', 'admin.tickets.reopened');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichTickets(array $rows): array
    {
        foreach ($rows as &$row) {
            if (($row['status'] ?? '') === 'open' && ($row['last_author_type'] ?? '') === 'player') {
                $row['_rowClass'] = 'is-waiting';
            }
        }

        unset($row);

        return $rows;
    }

    private function isValidHtmlBody(string $html): bool
    {
        if ($html === '' || mb_strlen($html) > self::MAX_HTML_BYTES) {
            return false;
        }

        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '' && mb_strlen($plain) <= self::MAX_PLAIN_CHARS;
    }

    private function setStatus(int $id, string $status, string $successKey): Response
    {
        $resource = $status === 'closed' ? 'content/tickets/close' : 'content/tickets/reopen';

        if ($redirect = $this->requireAdminResource($resource)) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/tickets/' . $id);
        }

        $ticket = $this->tickets->findById($id);

        if ($ticket === null) {
            $this->flash('error', $this->t('admin.tickets.not_found'));

            return $this->redirect('/admin/content/tickets');
        }

        $this->tickets->setStatus($id, $status);
        $this->auditChange(
            $status === 'closed' ? 'ticket.close' : 'ticket.reopen',
            'ticket',
            $id,
            ['status' => $ticket['status']],
            ['status' => $status],
        );
        $this->flash('success', $this->t($successKey));

        return $this->redirect('/admin/content/tickets/' . $id);
    }
}
