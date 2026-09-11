<?php

declare(strict_types=1);

namespace Mt2Cms\Event;

use Mt2Cms\Discord\DiscordWebhookService;

class EventService
{
    public function __construct(
        private EventRepository $events,
        private DiscordWebhookService $discord,
    ) {
    }

    /**
     * @param array{title: string, body: string, starts_at: string, ends_at: ?string, published: bool} $data
     */
    public function create(array $data): int
    {
        $id = $this->events->create($data);

        if ($data['published']) {
            $this->discord->notifyEventPublished($id, $data['title']);
        }

        return $id;
    }

    /**
     * @param array{title: string, body: string, starts_at: string, ends_at: ?string, published: bool} $data
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->events->findById($id);

        if ($existing === null) {
            return false;
        }

        $wasPublished = (int) ($existing['published'] ?? 0) === 1;
        $updated = $this->events->update($id, $data);

        if ($updated && $data['published'] && !$wasPublished) {
            $this->discord->notifyEventPublished($id, $data['title']);
        }

        return $updated;
    }

    public function publish(int $id): bool
    {
        $existing = $this->events->findById($id);

        if ($existing === null) {
            return false;
        }

        if ((int) ($existing['published'] ?? 0) === 1) {
            return true;
        }

        if (!$this->events->setPublished($id, true)) {
            return false;
        }

        $this->discord->notifyEventPublished($id, (string) $existing['title']);

        return true;
    }

    public function unpublish(int $id): bool
    {
        return $this->events->setPublished($id, false);
    }
}
