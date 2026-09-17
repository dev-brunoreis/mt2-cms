<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Service\EventSeedService;
use Mt2Cms\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class EventSeedServiceTest extends TestCase
{
    public function testSeedsPublishedClassicEventsForTheCurrentWeek(): void
    {
        $events = $this->createMock(EventRepository::class);
        $settings = $this->createMock(SettingsRepository::class);
        $now = new \DateTimeImmutable('2026-09-17 13:13:00');
        $created = [];

        $settings->method('get')->with(EventSeedService::SETTING_KEY)->willReturn(null);
        $events->method('countAll')->willReturn(0);
        $events->expects(self::exactly(5))
            ->method('create')
            ->willReturnCallback(function (array $data) use (&$created): int {
                $created[] = $data;

                return count($created);
            });
        $settings->expects(self::once())
            ->method('set')
            ->with(EventSeedService::SETTING_KEY, '1');

        $this->service($events, $settings, $now)->seedIfNeeded();

        self::assertCount(5, $created);
        self::assertSame(EventSeedService::TITLE_FISHING, $created[0]['title']);
        self::assertSame('2026-09-17 13:13:00', $created[0]['starts_at']);
        self::assertSame('2026-09-24 13:13:00', $created[0]['ends_at']);
        self::assertStringContainsString('Fishing spots', $created[0]['body']);

        self::assertSame(EventSeedService::TITLE_MOONLIGHT, $created[1]['title']);
        self::assertSame('2026-09-17 20:00:00', $created[1]['starts_at']);
        self::assertSame('2026-09-17 22:00:00', $created[1]['ends_at']);
        self::assertStringContainsString('Moonlight chests', $created[1]['body']);

        self::assertSame(EventSeedService::TITLE_DOUBLE_DROP, $created[2]['title']);
        self::assertSame('2026-09-18 18:00:00', $created[2]['starts_at']);
        self::assertSame('2026-09-20 23:59:00', $created[2]['ends_at']);
        self::assertStringContainsString('twice as much', $created[2]['body']);

        self::assertSame(EventSeedService::TITLE_OX, $created[3]['title']);
        self::assertSame('2026-09-19 16:00:00', $created[3]['starts_at']);
        self::assertSame('2026-09-19 17:00:00', $created[3]['ends_at']);
        self::assertStringContainsString('OX arena', $created[3]['body']);

        self::assertSame(EventSeedService::TITLE_GUILD_WAR, $created[4]['title']);
        self::assertSame('2026-09-20 20:00:00', $created[4]['starts_at']);
        self::assertSame('2026-09-20 22:00:00', $created[4]['ends_at']);
        self::assertStringContainsString('fortress', $created[4]['body']);

        foreach ($created as $row) {
            self::assertTrue($row['published']);
            self::assertNull($row['seo_title']);
            self::assertStringContainsString('<p>', $row['body']);
            self::assertGreaterThanOrEqual($row['starts_at'], $row['ends_at']);
        }
    }

    public function testUsesThisWeekendWhenAlreadyInsideDoubleDropWindow(): void
    {
        $events = $this->createMock(EventRepository::class);
        $settings = $this->createMock(SettingsRepository::class);
        $now = new \DateTimeImmutable('2026-09-19 11:00:00');
        $created = [];

        $settings->method('get')->with(EventSeedService::SETTING_KEY)->willReturn(null);
        $events->method('countAll')->willReturn(0);
        $events->method('create')->willReturnCallback(function (array $data) use (&$created): int {
            $created[] = $data;

            return count($created);
        });
        $settings->method('set');

        $this->service($events, $settings, $now)->seedIfNeeded();

        $drop = $this->rowByTitle($created, EventSeedService::TITLE_DOUBLE_DROP);
        self::assertSame('2026-09-18 18:00:00', $drop['starts_at']);
        self::assertSame('2026-09-20 23:59:00', $drop['ends_at']);

        $ox = $this->rowByTitle($created, EventSeedService::TITLE_OX);
        self::assertSame('2026-09-19 16:00:00', $ox['starts_at']);
        self::assertSame('2026-09-19 17:00:00', $ox['ends_at']);
    }

    public function testSkipsWhenAlreadySeeded(): void
    {
        $events = $this->createMock(EventRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(EventSeedService::SETTING_KEY)->willReturn('1');
        $events->expects(self::never())->method('countAll');
        $events->expects(self::never())->method('create');
        $settings->expects(self::never())->method('set');

        $this->service($events, $settings)->seedIfNeeded();
    }

    public function testMarksSeededWithoutInsertWhenEventsAlreadyExist(): void
    {
        $events = $this->createMock(EventRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(EventSeedService::SETTING_KEY)->willReturn(null);
        $events->method('countAll')->willReturn(2);
        $events->expects(self::never())->method('create');
        $settings->expects(self::once())
            ->method('set')
            ->with(EventSeedService::SETTING_KEY, '1');

        $this->service($events, $settings)->seedIfNeeded();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function rowByTitle(array $rows, string $title): array
    {
        foreach ($rows as $row) {
            if ($row['title'] === $title) {
                return $row;
            }
        }

        self::fail('Missing seeded event: ' . $title);
    }

    private function service(
        EventRepository $events,
        SettingsRepository $settings,
        ?\DateTimeImmutable $now = null,
    ): EventSeedService {
        return new EventSeedService($events, $settings, new HtmlSanitizer(), $now);
    }
}
