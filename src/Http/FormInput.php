<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

/**
 * Typed helpers for form POST/GET values (mirrors GridRequest conventions).
 */
final class FormInput
{
    public static function string(string $key, string $default = '', ?array $source = null): string
    {
        $bag = $source ?? $_POST;

        return trim((string) ($bag[$key] ?? $default));
    }

    public static function int(string $key, int $default = 0, ?array $source = null): int
    {
        $bag = $source ?? $_POST;
        $raw = $bag[$key] ?? $default;

        if (!is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    /**
     * @return array<string, mixed>
     */
    public static function postArray(): array
    {
        return is_array($_POST) ? $_POST : [];
    }
}
