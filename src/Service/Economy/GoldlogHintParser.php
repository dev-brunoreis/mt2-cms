<?php

declare(strict_types=1);

namespace Mt2Cms\Service\Economy;

/**
 * Parse goldlog.hint into item name + stack count for unit-price math.
 */
final class GoldlogHintParser
{
    /**
     * @return array{name: string, count: int}|null
     */
    public static function parse(string $hint): ?array
    {
        $hint = trim(preg_replace('/\s+/u', ' ', $hint) ?? '');

        if ($hint === '') {
            return null;
        }

        if (preg_match('/^(.*)\s+(\d{1,3})$/u', $hint, $m) === 1) {
            $name = trim($m[1]);
            $count = (int) $m[2];

            if ($name !== '' && $count >= 1 && $count <= 200) {
                return ['name' => $name, 'count' => $count];
            }
        }

        return ['name' => $hint, 'count' => 1];
    }

    public static function unitPrice(int $totalYang, int $count): ?int
    {
        if ($totalYang < 1 || $count < 1) {
            return null;
        }

        return (int) floor($totalYang / $count);
    }

    /**
     * Build a stable trade key for goldlog rows (no auto id).
     */
    public static function tradeKey(string $date, string $time, int $pid, int $what, string $hint): string
    {
        return hash('sha1', $date . '|' . $time . '|' . $pid . '|' . $what . '|' . $hint);
    }
}
