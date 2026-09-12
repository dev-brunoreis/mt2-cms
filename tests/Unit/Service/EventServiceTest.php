<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Service\DiscordWebhookService;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Service\EventService;
use PHPUnit\Framework\TestCase;

final class EventServiceTest extends TestCase
{
    public function testCreateNotifiesDiscordWhenPublished(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->method('create')->willReturn(42);

        $discord = $this->createMock(DiscordWebhookService::class);
        $discord->expects(self::once())
            ->method('notifyEventPublished')
            ->with(42, 'Launch');

        $service = new EventService($events, $discord);
        $id = $service->create([
            'title' => 'Launch',
            'body' => 'Details',
            'starts_at' => '2026-01-01 12:00:00',
            'ends_at' => null,
            'published' => true,
        ]);

        self::assertSame(42, $id);
    }

    public function testCreateSkipsDiscordWhenDraft(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->method('create')->willReturn(7);

        $discord = $this->createMock(DiscordWebhookService::class);
        $discord->expects(self::never())->method('notifyEventPublished');

        $service = new EventService($events, $discord);
        $service->create([
            'title' => 'Draft',
            'body' => 'Details',
            'starts_at' => '2026-01-01 12:00:00',
            'ends_at' => null,
            'published' => false,
        ]);
    }

    public function testUpdateNotifiesOnlyOnFirstPublish(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->method('findById')->willReturn([
            'id' => 5,
            'title' => 'Old title',
            'published' => 1,
        ]);
        $events->method('update')->willReturn(true);

        $discord = $this->createMock(DiscordWebhookService::class);
        $discord->expects(self::never())->method('notifyEventPublished');

        $service = new EventService($events, $discord);
        $service->update(5, [
            'title' => 'New title',
            'body' => 'Body',
            'starts_at' => '2026-01-01 12:00:00',
            'ends_at' => null,
            'published' => true,
        ]);
    }

    public function testUpdateNotifiesWhenTransitioningToPublished(): void
    {
        $events = $this->createMock(EventRepository::class);
        $events->method('findById')->willReturn([
            'id' => 5,
            'title' => 'Draft',
            'published' => 0,
        ]);
        $events->method('update')->willReturn(true);

        $discord = $this->createMock(DiscordWebhookService::class);
        $discord->expects(self::once())
            ->method('notifyEventPublished')
            ->with(5, 'Now live');

        $service = new EventService($events, $discord);
        $service->update(5, [
            'title' => 'Now live',
            'body' => 'Body',
            'starts_at' => '2026-01-01 12:00:00',
            'ends_at' => null,
            'published' => true,
        ]);
    }

}
