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

    /**
     * Race id from `player.job`. Pass skill_group to append Arahan / Partizan / etc.
     */
    public function job(mixed $job, mixed $skillGroup = null): string
    {
        $race = (int) $job;
        $classKey = 'job.' . Jobs::localeJobKey($race);
        $className = $this->translator->has($classKey)
            ? $this->translator->get($classKey)
            : $this->translator->get('job.unknown');

        $sex = Jobs::sex($race);
        $sexName = $sex !== null && $this->translator->has('sex.' . $sex)
            ? $this->translator->get('sex.' . $sex)
            : '';

        $label = $sexName !== ''
            ? $this->translator->get('job.with_sex', ['sex' => $sexName, 'job' => $className])
            : $className;

        $skill = $this->skill($race, $skillGroup);

        if ($skill === '') {
            return $label;
        }

        return $this->translator->get('job.with_skill', [
            'job' => $label,
            'skill' => $skill,
        ]);
    }

    public function skill(mixed $job, mixed $skillGroup): string
    {
        $group = (int) $skillGroup;

        if ($group < 1) {
            return '';
        }

        $key = 'job.skill.' . Jobs::localeJobKey((int) $job) . '.' . $group;

        if (!$this->translator->has($key)) {
            return '';
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
     * Duration in seconds → compact "2h 5m" / "45s".
     */
    public function duration(mixed $seconds): string
    {
        $total = max(0, (int) $seconds);

        if ($total < 60) {
            return $this->translator->get('duration.seconds', ['n' => $total]);
        }

        $days = intdiv($total, 86400);
        $hours = intdiv($total % 86400, 3600);
        $mins = intdiv($total % 3600, 60);
        $secs = $total % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $this->translator->get('playtime.days', ['n' => $days]);
        }

        if ($hours > 0) {
            $parts[] = $this->translator->get('playtime.hours', ['n' => $hours]);
        }

        if ($mins > 0) {
            $parts[] = $this->translator->get('playtime.minutes', ['n' => $mins]);
        }

        if ($secs > 0 && $days === 0 && $hours === 0) {
            $parts[] = $this->translator->get('duration.seconds', ['n' => $secs]);
        }

        return implode(' ', $parts);
    }

    /**
     * Unix timestamp → same relative + calendar format as datetime().
     */
    public function unixDate(mixed $timestamp): string
    {
        $ts = (int) $timestamp;

        if ($ts < 1) {
            return $this->translator->get('datetime.never');
        }

        $at = (new \DateTimeImmutable('@' . $ts))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $this->datetime($at->format('Y-m-d H:i:s'));
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

    /**
     * Proto enum token (ITEM_WEAPON, MONSTER, S_KNIGHT, …) using form labels.
     */
    public function protoToken(mixed $token): string
    {
        $token = trim((string) $token);

        if ($token === '') {
            return '—';
        }

        $key = 'admin.proto.tokens.' . $token;

        return $this->translator->has($key) ? $this->translator->get($key) : $token;
    }
}
