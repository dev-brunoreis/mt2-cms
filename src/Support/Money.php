<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

final class Money
{
    public const FORMAT_DOT = 'dot';
    public const FORMAT_COMMA = 'comma';
    public const FORMAT_SPACE = 'space';

    /** @var list<string> */
    public const FORMATS = [self::FORMAT_DOT, self::FORMAT_COMMA, self::FORMAT_SPACE];

    public static function normalizeFormat(string $format): string
    {
        return in_array($format, self::FORMATS, true) ? $format : self::FORMAT_DOT;
    }

    public static function formatDecimal(int $cents, string $format = self::FORMAT_DOT): string
    {
        $separators = self::separators($format);

        return number_format($cents / 100, 2, $separators['decimal'], $separators['thousands']);
    }

    public static function example(string $format): string
    {
        return self::formatDecimal(123456, $format);
    }

    /**
     * Accepts 500, 500.00, 500,00, 1.234,56, or 1,234.56.
     *
     * @throws \InvalidArgumentException
     */
    public static function centsFromInput(string $raw): int
    {
        $raw = trim(str_replace(["\u{00A0}", ' '], '', $raw));

        if ($raw === '') {
            throw new \InvalidArgumentException('admin.packages.price_invalid');
        }

        if (!preg_match('/^-?[0-9.,]+$/', $raw)) {
            throw new \InvalidArgumentException('admin.packages.price_invalid');
        }

        $normalized = self::normalizeDecimal($raw);

        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('admin.packages.price_invalid');
        }

        $cents = (int) round((float) $normalized * 100);

        if ($cents < 1) {
            throw new \InvalidArgumentException('admin.packages.price_invalid');
        }

        return $cents;
    }

    /**
     * @return array{decimal: string, thousands: string}
     */
    public static function separators(string $format): array
    {
        return match (self::normalizeFormat($format)) {
            self::FORMAT_COMMA => ['decimal' => ',', 'thousands' => '.'],
            self::FORMAT_SPACE => ['decimal' => ',', 'thousands' => ' '],
            default => ['decimal' => '.', 'thousands' => ','],
        };
    }

    private static function normalizeDecimal(string $raw): string
    {
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                return str_replace(['.', ','], ['', '.'], $raw);
            }

            return str_replace(',', '', $raw);
        }

        if ($lastComma !== false) {
            return str_replace(',', '.', $raw);
        }

        return $raw;
    }
}
