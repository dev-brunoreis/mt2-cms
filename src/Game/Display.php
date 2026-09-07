<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

use Mt2Cms\I18n\Translator;

class Display
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    public function job(mixed $job): string
    {
        $key = 'job.' . (int) $job;

        if (!$this->translator->has($key)) {
            return $this->translator->get('job.unknown');
        }

        return $this->translator->get($key);
    }

    public function empire(mixed $empire): string
    {
        $key = 'empire.' . (int) $empire;

        if (!$this->translator->has($key)) {
            return $this->translator->get('empire.none');
        }

        return $this->translator->get($key);
    }

    /**
     * Playtime is stored in minutes on the player table.
     */
    public function playtime(mixed $minutes): string
    {
        $total = max(0, (int) $minutes);
        $days = intdiv($total, 1440);
        $hours = intdiv($total % 1440, 60);
        $mins = $total % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $this->translator->get('playtime.days', ['n' => $days]);
        }

        if ($hours > 0 || $days > 0) {
            $parts[] = $this->translator->get('playtime.hours', ['n' => $hours]);
        }

        $parts[] = $this->translator->get('playtime.minutes', ['n' => $mins]);

        return implode(' ', $parts);
    }

    /**
     * MySQL DATETIME → relative time with calendar date.
     */
    public function datetime(mixed $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return $this->translator->get('datetime.never');
        }

        try {
            $at = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return $this->translator->get('datetime.never');
        }

        $now = new \DateTimeImmutable('now');
        $diff = $now->getTimestamp() - $at->getTimestamp();
        $format = $this->translator->get('datetime.calendar_format');
        $calendar = $at->format($format !== 'datetime.calendar_format' ? $format : 'Y-m-d H:i');

        if ($diff < 0) {
            return $calendar;
        }

        if ($diff < 60) {
            $relative = $this->translator->get('datetime.now');
        } elseif ($diff < 3600) {
            $n = intdiv($diff, 60);
            $relative = $this->translator->get('datetime.minutes', ['n' => $n]);
        } elseif ($diff < 86400) {
            $n = intdiv($diff, 3600);
            $relative = $this->translator->get('datetime.hours', ['n' => $n]);
        } elseif ($diff < 2592000) {
            $n = intdiv($diff, 86400);
            $relative = $this->translator->get('datetime.days', ['n' => $n]);
        } elseif ($diff < 31536000) {
            $n = intdiv($diff, 2592000);
            $relative = $this->translator->get('datetime.months', ['n' => $n]);
        } else {
            $n = intdiv($diff, 31536000);
            $relative = $this->translator->get('datetime.years', ['n' => $n]);
        }

        return $this->translator->get('datetime.combined', [
            'relative' => $relative,
            'calendar' => $calendar,
        ]);
    }
}
