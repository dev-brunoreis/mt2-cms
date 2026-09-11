<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\RateLimiter;
use Mt2Cms\Discord\DiscordWebhookService;
use Mt2Cms\Http\Request;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\TicketRepository;
use Mt2Cms\Service\TicketUploadService;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Theme\ThemeEngine;

class TicketController extends Controller
{
    private const PER_PAGE = 20;
    private const MAX_HTML_BYTES = 20000;
    private const MAX_PLAIN_CHARS = 5000;

    private RateLimiter $rateLimiter;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private TicketRepository $tickets,
        private TicketUploadService $uploads,
        private HtmlSanitizer $sanitizer,
        private DiscordWebhookService $discord,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
        $this->rateLimiter = new RateLimiter(8, 900);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $accountId = (int) $this->auth->id();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->tickets->countForAccount($accountId);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->view('tickets', [
            'title' => $this->t('tickets.title'),
            'tickets' => $this->tickets->listForAccount($accountId, $page, self::PER_PAGE),
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
            'activeTicketNav' => 'list',
        ]);
    }

    public function create(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        return $this->view('ticket-form', [
            'title' => $this->t('tickets.create'),
            'subject' => '',
            'body' => '',
            'error' => null,
            'activeTicketNav' => 'new',
            'maxAttachments' => TicketUploadService::MAX_FILES,
            'maxAttachmentMb' => (int) (TicketUploadService::MAX_BYTES / 1_048_576),
        ]);
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/account/tickets/new');
        }

        $subject = trim((string) ($_POST['subject'] ?? ''));
        $rawBody = (string) ($_POST['body'] ?? '');
        $body = $this->sanitizer->sanitize($rawBody, false);
        $files = $this->uploads->normalizeUploads(
            isset($_FILES['evidence']) && is_array($_FILES['evidence']) ? $_FILES['evidence'] : null,
        );

        $formError = function (string $errorKey, int $status = 422) use ($subject, $body): Response {
            return $this->view('ticket-form', [
                'title' => $this->t('tickets.create'),
                'subject' => $subject,
                'body' => $body,
                'error' => $this->t($errorKey),
                'activeTicketNav' => 'new',
                'maxAttachments' => TicketUploadService::MAX_FILES,
                'maxAttachmentMb' => (int) (TicketUploadService::MAX_BYTES / 1_048_576),
            ], $status);
        };

        $bucket = 'ticket_create:' . ($this->auth->id() ?? 0) . ':' . $this->clientIp();

        if ($this->rateLimiter->tooManyAttempts($bucket)) {
            return $formError('tickets.rate_limited', 429);
        }

        if ($subject === '' || mb_strlen($subject) > 200 || !$this->isValidHtmlBody($body)) {
            return $formError('tickets.invalid');
        }

        try {
            $this->uploads->assertValidBatch($files);
        } catch (\InvalidArgumentException $e) {
            return $formError($e->getMessage());
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->uploads->store($file);
            }

            $created = $this->tickets->create(
                (int) $this->auth->id(),
                (string) $this->auth->login(),
                $subject,
                $body,
            );

            foreach ($stored as $file) {
                $this->tickets->addAttachment($created['ticket_id'], $created['message_id'], $file);
            }
        } catch (\InvalidArgumentException $e) {
            foreach ($stored as $file) {
                $this->uploads->delete($file['stored_name']);
            }

            return $formError($e->getMessage());
        } catch (\Throwable $e) {
            foreach ($stored as $file) {
                $this->uploads->delete($file['stored_name']);
            }

            return $formError('tickets.attachment_failed');
        }

        $this->rateLimiter->hit($bucket);
        $this->discord->notifyNewTicket(
            (int) $created['ticket_id'],
            $subject,
            (string) $this->auth->login(),
        );
        $this->flash('success', $this->t('tickets.created'));

        return $this->redirect('/account/tickets/' . $created['ticket_id']);
    }

    public function show(string $id): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $ticket = $this->tickets->findForAccount((int) $id, (int) $this->auth->id());

        if ($ticket === null) {
            $this->flash('error', $this->t('tickets.not_found'));

            return $this->redirect('/account/tickets');
        }

        return $this->view('ticket-show', [
            'title' => (string) $ticket['subject'],
            'ticket' => $ticket,
            'messages' => $this->tickets->messages((int) $ticket['id']),
            'attachmentsByMessage' => $this->tickets->attachmentsGroupedByMessage((int) $ticket['id']),
            'attachmentBase' => '/account/tickets/' . (int) $ticket['id'] . '/attachments/',
            'activeTicketNav' => 'list',
        ]);
    }

    public function downloadAttachment(string $id, string $attachmentId): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $ticket = $this->tickets->findForAccount((int) $id, (int) $this->auth->id());

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
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/account/tickets/' . (int) $id);
        }

        $ticket = $this->tickets->findForAccount((int) $id, (int) $this->auth->id());

        if ($ticket === null) {
            $this->flash('error', $this->t('tickets.not_found'));

            return $this->redirect('/account/tickets');
        }

        if ((string) $ticket['status'] === 'closed') {
            $this->flash('error', $this->t('tickets.closed'));

            return $this->redirect('/account/tickets/' . (int) $id);
        }

        $body = $this->sanitizer->sanitize((string) ($_POST['body'] ?? ''), false);

        if (!$this->isValidHtmlBody($body)) {
            $this->flash('error', $this->t('tickets.invalid_reply'));

            return $this->redirect('/account/tickets/' . (int) $id);
        }

        $this->tickets->addMessage(
            (int) $ticket['id'],
            'user',
            (int) $this->auth->id(),
            (string) $this->auth->login(),
            $body,
        );
        $this->tickets->setStatus((int) $ticket['id'], 'open');
        $this->tickets->touch((int) $ticket['id']);
        $this->flash('success', $this->t('tickets.replied'));

        return $this->redirect('/account/tickets/' . (int) $id);
    }

    public function close(string $id): Response
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/account/tickets/' . (int) $id);
        }

        $ticket = $this->tickets->findForAccount((int) $id, (int) $this->auth->id());

        if ($ticket === null) {
            $this->flash('error', $this->t('tickets.not_found'));

            return $this->redirect('/account/tickets');
        }

        $this->tickets->setStatus((int) $ticket['id'], 'closed');
        $this->flash('success', $this->t('tickets.closed_ok'));

        return $this->redirect('/account/tickets/' . (int) $id);
    }

    private function isValidHtmlBody(string $html): bool
    {
        if ($html === '' || mb_strlen($html) > self::MAX_HTML_BYTES) {
            return false;
        }

        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '' && mb_strlen($plain) <= self::MAX_PLAIN_CHARS;
    }

    private function clientIp(): string
    {
        return Request::clientIp();
    }
}
