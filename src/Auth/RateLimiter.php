<?php

declare(strict_types=1);

namespace Mt2Cms\Auth;

/**
 * Simple session + IP rate limiter for auth endpoints (no Redis required).
 */
class RateLimiter
{
    private const SESSION_KEY = '_rate_limit';

    public function __construct(
        private int $maxAttempts = 10,
        private int $windowSeconds = 900,
    ) {
    }

    public function tooManyAttempts(string $bucket): bool
    {
        $this->prune($bucket);

        return count($this->hits($bucket)) >= $this->maxAttempts;
    }

    public function hit(string $bucket): void
    {
        $this->prune($bucket);

        $all = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($all)) {
            $all = [];
        }

        $hits = $all[$bucket] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }

        $hits[] = time();
        $all[$bucket] = $hits;
        $_SESSION[self::SESSION_KEY] = $all;
    }

    public function clear(string $bucket): void
    {
        $all = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($all)) {
            return;
        }

        unset($all[$bucket]);
        $_SESSION[self::SESSION_KEY] = $all;
    }

    /**
     * @return list<int>
     */
    private function hits(string $bucket): array
    {
        $all = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($all)) {
            return [];
        }

        $hits = $all[$bucket] ?? [];
        if (!is_array($hits)) {
            return [];
        }

        return array_values(array_map('intval', $hits));
    }

    private function prune(string $bucket): void
    {
        $cutoff = time() - $this->windowSeconds;
        $hits = array_values(array_filter(
            $this->hits($bucket),
            static fn (int $at): bool => $at >= $cutoff,
        ));

        $all = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($all)) {
            $all = [];
        }

        if ($hits === []) {
            unset($all[$bucket]);
        } else {
            $all[$bucket] = $hits;
        }

        $_SESSION[self::SESSION_KEY] = $all;
    }
}
