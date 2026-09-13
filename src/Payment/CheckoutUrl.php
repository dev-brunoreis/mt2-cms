<?php

declare(strict_types=1);

namespace Mt2Cms\Payment;

final class CheckoutUrl
{
    public static function isSafe(string $url): bool
    {
        if ($url === '' || strpbrk($url, "\r\n\0") !== false) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        return $scheme === 'https' && $host !== '';
    }
}
