<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class AdminAuditMeta
{
    private const BLOCKED = ['password', 'pin', 'hash', 'social_id', 'securitycode', '_csrf', 'passwd'];

    private const MAX_STRING = 400;

    private const MAX_ENCODED_VALUE = 8000;

    /**
     * @param array<string|int, mixed> $before
     * @param array<string|int, mixed> $after
     * @return array<string, mixed>
     */
    public static function changed(array $before, array $after): array
    {
        $beforeOut = [];
        $afterOut = [];
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);

        foreach ($keys as $key) {
            $hasLeft = array_key_exists($key, $before);
            $hasRight = array_key_exists($key, $after);

            if ($hasLeft && $hasRight && self::valuesEqual($before[$key], $after[$key])) {
                continue;
            }

            $name = (string) $key;

            if ($hasLeft) {
                $beforeOut[$name] = self::compactValue($before[$key]);
            }

            if ($hasRight) {
                $afterOut[$name] = self::compactValue($after[$key]);
            }
        }

        $meta = [];

        if ($beforeOut !== []) {
            $meta['before'] = $beforeOut;
        }

        if ($afterOut !== []) {
            $meta['after'] = $afterOut;
        }

        return $meta;
    }

    /**
     * @param array<string, mixed>|null $meta
     * @return array<string, mixed>|null
     */
    public static function sanitize(?array $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        $clean = self::sanitizeArray($meta);

        return $clean === [] ? null : $clean;
    }

    /**
     * @return array{before: list<array{key: string, value: string}>, after: list<array{key: string, value: string}>}
     */
    public static function displayColumns(mixed $meta): array
    {
        $decoded = self::decode($meta);

        if ($decoded === null) {
            return ['before' => [], 'after' => []];
        }

        $before = [];
        $after = [];
        $extra = [];

        foreach ($decoded as $key => $value) {
            if ($key === 'before' && is_array($value)) {
                $before = $value;

                continue;
            }

            if ($key === 'after' && is_array($value)) {
                $after = $value;

                continue;
            }

            $extra[(string) $key] = $value;
        }

        if ($before === [] && $after === [] && $extra !== []) {
            $after = $extra;
            $extra = [];
        }

        foreach ($extra as $key => $value) {
            $after[$key] = $value;
        }

        return [
            'before' => self::pairs($before),
            'after' => self::pairs($after),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(mixed $meta): ?array
    {
        if ($meta === null || $meta === '') {
            return null;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array<string|int, mixed> $values
     * @return list<array{key: string, value: string}>
     */
    private static function pairs(array $values): array
    {
        $pairs = [];

        foreach ($values as $key => $value) {
            $pairs[] = [
                'key' => (string) $key,
                'value' => self::formatValue($value),
            ];
        }

        return $pairs;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($json)) {
                return '';
            }

            return self::truncate($json, 200);
        }

        return self::truncate((string) $value, 200);
    }

    private static function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 3) . '...';
    }

    /**
     * @param array<string|int, mixed> $meta
     * @return array<string, mixed>
     */
    private static function sanitizeArray(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            $name = (string) $key;

            if (self::isBlockedKey($name)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$name] = is_string($value) ? self::truncate($value, self::MAX_STRING) : $value;

                continue;
            }

            if (is_array($value)) {
                $clean[$name] = self::sanitizeArray($value);
            }
        }

        return $clean;
    }

    private static function isBlockedKey(string $key): bool
    {
        $lower = strtolower($key);

        if (in_array($lower, self::BLOCKED, true)) {
            return true;
        }

        return str_contains($lower, 'password') || str_contains($lower, 'passwd');
    }

    private static function valuesEqual(mixed $left, mixed $right): bool
    {
        return self::comparable($left) === self::comparable($right);
    }

    private static function comparable(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];

            foreach ($value as $k => $v) {
                $out[$k] = self::comparable($v);
            }

            return $out;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return 0 + $value;
        }

        if (is_string($value) && is_numeric($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return 0 + $value;
        }

        return $value;
    }

    private static function compactValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_string($value)) {
                return self::truncate($value, self::MAX_STRING);
            }

            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE);

        if (is_string($json) && strlen($json) > self::MAX_ENCODED_VALUE) {
            return ['truncated' => true, 'bytes' => strlen($json)];
        }

        return $value;
    }
}
