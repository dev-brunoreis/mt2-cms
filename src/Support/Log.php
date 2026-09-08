<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

class Log
{
    public static function error(string $channel, string $message, ?\Throwable $exception = null): void
    {
        $line = sprintf('[%s] %s: %s', date('c'), $channel, $message);

        if ($exception !== null) {
            $line .= sprintf(
                ' (%s: %s)',
                $exception::class,
                self::sanitize($exception->getMessage()),
            );
        }

        error_log($line);
    }

    private static function sanitize(string $message): string
    {
        return preg_replace('/password|secret|token/i', '***', $message) ?? $message;
    }
}
