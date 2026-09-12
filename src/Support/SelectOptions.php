<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

final class SelectOptions
{
    /**
     * Sort a list of option arrays by a string field (usually the visible label).
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function sortBy(array $items, string $key = 'label'): array
    {
        usort($items, static function (array $left, array $right) use ($key): int {
            return self::compare((string) ($left[$key] ?? ''), (string) ($right[$key] ?? ''));
        });

        return $items;
    }

    /**
     * Sort a value => label map by the visible label, keeping keys.
     *
     * @param array<array-key, string> $options
     * @return array<array-key, string>
     */
    public static function sortMap(array $options): array
    {
        uasort($options, [self::class, 'compare']);

        return $options;
    }

    public static function compare(string $left, string $right): int
    {
        return self::normalize($left) <=> self::normalize($right);
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
