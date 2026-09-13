<?php

declare(strict_types=1);

namespace Mt2Cms\Service\Economy;

/**
 * Parse `log.hint` for player-shop rows:
 * "Lunar Sword+9 2(Test) 40 1" → name, other player, yang, count.
 */
final class ItemLogHintParser
{
    /**
     * @return array{name: string, other_pid: int, other_name: string, yang: int, count: int}|null
     */
    public static function parseShop(string $hint): ?array
    {
        $hint = trim(preg_replace('/\s+/u', ' ', $hint) ?? '');

        if ($hint === '') {
            return null;
        }

        if (preg_match('/^(.*)\s+(\d+)\((.*)\)\s+(\d+)\s+(\d+)$/u', $hint, $m) !== 1) {
            return null;
        }

        $name = trim($m[1]);
        $yang = (int) $m[4];
        $count = (int) $m[5];

        if ($name === '' || $yang < 1 || $count < 1 || $count > 200) {
            return null;
        }

        return [
            'name' => $name,
            'other_pid' => (int) $m[2],
            'other_name' => trim($m[3]),
            'yang' => $yang,
            'count' => $count,
        ];
    }
}
