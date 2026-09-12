<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Service\EventService;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Theme\ThemeEngine;

class AdminEventsController extends AdminController
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
        private EventRepository $events,
        private EventService $eventService,
        private HtmlSanitizer $sanitizer,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->events->gridDefinition()->spec();
        $grid = GridRunner::fetch(
            $spec,
            $this->gridQuery($spec),
            fn ($q) => $this->events->countForGrid($q),
            fn ($q) => $this->events->listForGrid($q),
        );

        return $this->adminView('events', 'pages/events.twig', [
            'title' => $this->t('admin.events.title'),
            'pageLead' => $this->t('admin.events.lead'),
            'headerHref' => '/admin/content/events/new',
            'headerActionLabel' => $this->t('admin.events.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->events->gridDefinition()->spec(),
            '/admin/content/events',
            [
                'publish' => fn (int $id): bool => $this->massPublish($id),
                'unpublish' => fn (int $id): bool => $this->eventService->unpublish($id),
                'delete' => fn (int $id): bool => $this->events->delete($id),
            ],
            'event',
            'admin.events.mass_done',
            'content/events/mass',
        );
    }

    public function create(): Response
    {
        return $this->formView($this->prefill());
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminResource('content/events/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/events/new');
        }

        $input = $this->formInput();

        try {
            $this->validateInput($input);
            $data = $this->toPersistData($input);
            $eventId = $this->eventService->create($data);
            $this->audit('event.create', 'event', $eventId);
            $this->flash('success', $this->t('admin.events.created'));

            return $this->redirect('/admin/content/events');
        } catch (\InvalidArgumentException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function edit(string $id): Response
    {
        $event = $this->events->findById((int) $id);

        if ($event === null) {
            $this->flash('error', $this->t('admin.events.not_found'));

            return $this->redirect('/admin/content/events');
        }

        return $this->formView([
            'id' => (int) $event['id'],
            'title' => (string) $event['title'],
            'body' => (string) $event['body'],
            'starts_at' => $this->toDatetimeLocal((string) $event['starts_at']),
            'ends_at' => $event['ends_at'] !== null ? $this->toDatetimeLocal((string) $event['ends_at']) : '',
            'published' => (int) $event['published'] === 1,
        ]);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('content/events/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/content/events/' . (int) $id);
        }

        $existing = $this->events->findById((int) $id);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.events.not_found'));

            return $this->redirect('/admin/content/events');
        }

        $input = $this->formInput();
        $input['id'] = (int) $id;

        try {
            $this->validateInput($input);
            $data = $this->toPersistData($input);
            $this->eventService->update((int) $id, $data);
            $this->auditChange('event.update', 'event', (int) $id, [
                'title' => $existing['title'],
                'starts_at' => $existing['starts_at'],
                'ends_at' => $existing['ends_at'],
                'published' => (int) $existing['published'],
            ], [
                'title' => $data['title'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'published' => $data['published'],
            ]);
            $this->flash('success', $this->t('admin.events.update_ok'));

            return $this->redirect('/admin/content/events/' . (int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function formView(array $event = [], ?string $error = null, int $status = 200): Response
    {
        $isEdit = isset($event['id']);

        return $this->adminView('events', 'pages/event-form.twig', [
            'title' => $isEdit ? $this->t('admin.events.edit') : $this->t('admin.events.create_title'),
            'pageLead' => $this->t('admin.events.form_lead'),
            'formId' => 'admin-event-form',
            'saveLabel' => $this->t('admin.save'),
            'event' => $event,
            'isEdit' => $isEdit,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array{title: string, body: string, starts_at: string, ends_at: string, published: bool}
     */
    private function prefill(): array
    {
        return [
            'title' => '',
            'body' => '',
            'starts_at' => '',
            'ends_at' => '',
            'published' => false,
        ];
    }

    /**
     * @return array{title: string, body: string, starts_at: string, ends_at: string, published: bool}
     */
    private function formInput(): array
    {
        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'body' => (string) ($_POST['body'] ?? ''),
            'starts_at' => trim((string) ($_POST['starts_at'] ?? '')),
            'ends_at' => trim((string) ($_POST['ends_at'] ?? '')),
            'published' => isset($_POST['published']),
        ];
    }

    /**
     * @param array{title: string, body: string, starts_at: string, ends_at: string, published: bool} $input
     * @return array{title: string, body: string, starts_at: string, ends_at: ?string, published: bool}
     */
    private function toPersistData(array $input): array
    {
        return [
            'title' => $input['title'],
            'body' => $this->sanitizer->sanitize($input['body']),
            'starts_at' => $this->parseDatetime($input['starts_at']),
            'ends_at' => $input['ends_at'] !== '' ? $this->parseDatetime($input['ends_at']) : null,
            'published' => $input['published'],
        ];
    }

    /**
     * @param array{title: string, body: string, starts_at: string, ends_at: string, published: bool} $input
     */
    private function validateInput(array $input): void
    {
        if ($input['title'] === '' || mb_strlen($input['title']) > 200) {
            throw new \InvalidArgumentException('admin.events.invalid_title');
        }

        $body = trim(strip_tags($input['body']));

        if ($body === '') {
            throw new \InvalidArgumentException('admin.events.invalid_body');
        }

        $startsAt = $this->parseDatetime($input['starts_at']);
        $endsAt = $input['ends_at'] !== '' ? $this->parseDatetime($input['ends_at']) : null;

        if ($endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            throw new \InvalidArgumentException('admin.events.invalid_dates');
        }
    }

    private function parseDatetime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('admin.events.invalid_starts_at');
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new \InvalidArgumentException('admin.events.invalid_starts_at');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function toDatetimeLocal(string $value): string
    {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $timestamp);
    }

    private function massPublish(int $id): bool
    {
        if (!$this->eventService->publish($id)) {
            return false;
        }

        $this->audit('event.publish', 'event', $id);

        return true;
    }
}
