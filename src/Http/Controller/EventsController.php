<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;

class EventsController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        private EventRepository $events,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator);
    }

    public function index(): Response
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->events->countPublished();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->view('events', [
            'title' => $this->t('events.title'),
            'events' => $this->events->listPublished($page, self::PER_PAGE),
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function show(string $id): Response
    {
        $event = $this->events->findPublishedById((int) $id);

        if ($event === null) {
            return $this->view('event-show', [
                'title' => $this->t('events.not_found'),
                'event' => null,
                'notFound' => true,
            ], 404);
        }

        return $this->view('event-show', [
            'title' => (string) $event['title'],
            'event' => $event,
            'notFound' => false,
        ]);
    }
}
