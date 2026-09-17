<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Support\HtmlSanitizer;

/**
 * Seeds classic Metin2 events once (empty table + flag), dated from the current week.
 */
class EventSeedService
{
    public const SETTING_KEY = 'events_seeded';

    public const TITLE_FISHING = 'Fishing Event';

    public const TITLE_MOONLIGHT = 'Moonlight Treasure Chest';

    public const TITLE_DOUBLE_DROP = 'Double Drop Weekend';

    public const TITLE_OX = 'OX Event';

    public const TITLE_GUILD_WAR = 'Guild War';

    public function __construct(
        private EventRepository $events,
        private SettingsRepository $settings,
        private HtmlSanitizer $sanitizer,
        private ?\DateTimeImmutable $now = null,
    ) {
    }

    public function seedIfNeeded(): void
    {
        if ($this->settings->get(self::SETTING_KEY) === '1') {
            return;
        }

        if ($this->events->countAll() > 0) {
            $this->settings->set(self::SETTING_KEY, '1');

            return;
        }

        foreach ($this->catalog($this->now()) as $event) {
            $this->events->create([
                'title' => $event['title'],
                'body' => $this->sanitizer->sanitize($event['body']),
                'starts_at' => $event['starts_at'],
                'ends_at' => $event['ends_at'],
                'published' => true,
                'seo_title' => null,
                'seo_description' => null,
                'seo_og_image' => null,
            ]);
        }

        $this->settings->set(self::SETTING_KEY, '1');
    }

    /**
     * @return list<array{title: string, body: string, starts_at: string, ends_at: string}>
     */
    private function catalog(\DateTimeImmutable $now): array
    {
        [$moonlightStart, $moonlightEnd] = $this->tonightWindow($now, 20, 0, 22, 0);
        [$dropStart, $dropEnd] = $this->weekendWindow($now);
        [$oxStart, $oxEnd] = $this->nextOccurrence($now, 6, 16, 0, 17, 0);
        [$warStart, $warEnd] = $this->nextOccurrence($now, 7, 20, 0, 22, 0);

        return [
            [
                'title' => self::TITLE_FISHING,
                'body' => $this->fishingBody(),
                'starts_at' => $this->format($now),
                'ends_at' => $this->format($now->modify('+7 days')),
            ],
            [
                'title' => self::TITLE_MOONLIGHT,
                'body' => $this->moonlightBody(),
                'starts_at' => $this->format($moonlightStart),
                'ends_at' => $this->format($moonlightEnd),
            ],
            [
                'title' => self::TITLE_DOUBLE_DROP,
                'body' => $this->doubleDropBody(),
                'starts_at' => $this->format($dropStart),
                'ends_at' => $this->format($dropEnd),
            ],
            [
                'title' => self::TITLE_OX,
                'body' => $this->oxBody(),
                'starts_at' => $this->format($oxStart),
                'ends_at' => $this->format($oxEnd),
            ],
            [
                'title' => self::TITLE_GUILD_WAR,
                'body' => $this->guildWarBody(),
                'starts_at' => $this->format($warStart),
                'ends_at' => $this->format($warEnd),
            ],
        ];
    }

    private function fishingBody(): string
    {
        return <<<'HTML'
<p>Fishing spots are overflowing. Catch rates are up, and rare fish are in the water all week.</p>
<h3>How to take part</h3>
<ul>
<li>Buy a fishing rod from the village fisherman if you do not have one</li>
<li>Use bait for a better catch</li>
<li>Fish in Map 1, the desert lake, and other marked spots</li>
</ul>
<p>Sell surplus fish or keep the rare ones for cooking and trades.</p>
HTML;
    }

    private function moonlightBody(): string
    {
        return <<<'HTML'
<p>Moonlight chests are appearing across the hunting maps. Smash them for Yang, items, and rare materials.</p>
<h3>Where to look</h3>
<ul>
<li>Village outskirts and the first hunting maps</li>
<li>Desert and mid-level zones later in the hour</li>
<li>Chests despawn when the event ends — do not wait</li>
</ul>
HTML;
    }

    private function doubleDropBody(): string
    {
        return <<<'HTML'
<p>Every monster drops twice as much until Sunday night. Hunt, farm, and fill your warehouse.</p>
<ul>
<li>All maps and dungeons</li>
<li>Stacks with your usual drop bonuses</li>
<li>Yang drops are included</li>
</ul>
HTML;
    }

    private function oxBody(): string
    {
        return <<<'HTML'
<p>The OX arena is open. Warp in when the event starts and stand on O (true) or X (false) for each question.</p>
<h3>How it works</h3>
<ul>
<li>Pick a side before time runs out</li>
<li>Wrong answers fall through the floor</li>
<li>Last players standing share the prize pool</li>
</ul>
<p>Bring a mount if you have one — the arena is large.</p>
HTML;
    }

    private function guildWarBody(): string
    {
        return <<<'HTML'
<p>Guilds fight for the fortress. Register with your guild leader before the gates open.</p>
<ul>
<li>Only guild members can enter the war map</li>
<li>Hold the core to claim the fortress</li>
<li>Rewards go to the winning guild</li>
</ul>
HTML;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function tonightWindow(\DateTimeImmutable $now, int $startHour, int $startMinute, int $endHour, int $endMinute): array
    {
        $start = $now->setTime($startHour, $startMinute, 0);
        $end = $now->setTime($endHour, $endMinute, 0);

        if ($now <= $end) {
            return [$start, $end];
        }

        $next = $now->modify('+1 day');

        return [$next->setTime($startHour, $startMinute, 0), $next->setTime($endHour, $endMinute, 0)];
    }

    /**
     * Friday 18:00 through Sunday 23:59 of the current or next weekend.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function weekendWindow(\DateTimeImmutable $now): array
    {
        $weekday = (int) $now->format('N');
        $fridayStart = $now->setTime(18, 0, 0);
        $sundayEnd = $now->setTime(23, 59, 0);

        if ($weekday === 5 && $now >= $fridayStart) {
            return [$fridayStart, $now->modify('next sunday')->setTime(23, 59, 0)];
        }

        if ($weekday === 6) {
            return [$now->modify('last friday')->setTime(18, 0, 0), $now->modify('sunday')->setTime(23, 59, 0)];
        }

        if ($weekday === 7 && $now <= $sundayEnd) {
            return [$now->modify('last friday')->setTime(18, 0, 0), $sundayEnd];
        }

        $start = $this->atNext($now, 5, 18, 0);

        return [$start, $start->modify('next sunday')->setTime(23, 59, 0)];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function nextOccurrence(
        \DateTimeImmutable $now,
        int $isoWeekday,
        int $startHour,
        int $startMinute,
        int $endHour,
        int $endMinute,
    ): array {
        $start = $now->setTime($startHour, $startMinute, 0);
        $end = $now->setTime($endHour, $endMinute, 0);
        $current = (int) $now->format('N');

        if ($current === $isoWeekday && $now <= $end) {
            return [$start, $end];
        }

        $daysAhead = ($isoWeekday - $current + 7) % 7;

        if ($daysAhead === 0) {
            $daysAhead = 7;
        }

        $day = $now->modify('+' . $daysAhead . ' days');

        return [$day->setTime($startHour, $startMinute, 0), $day->setTime($endHour, $endMinute, 0)];
    }

    private function atNext(\DateTimeImmutable $now, int $isoWeekday, int $hour, int $minute): \DateTimeImmutable
    {
        $candidate = $now->setTime($hour, $minute, 0);
        $current = (int) $now->format('N');

        if ($current === $isoWeekday && $now <= $candidate) {
            return $candidate;
        }

        $daysAhead = ($isoWeekday - $current + 7) % 7;

        if ($daysAhead === 0) {
            $daysAhead = 7;
        }

        return $now->modify('+' . $daysAhead . ' days')->setTime($hour, $minute, 0);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->now ?? new \DateTimeImmutable('now');
    }

    private function format(\DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i:s');
    }
}
