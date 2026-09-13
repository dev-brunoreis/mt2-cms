<?php

declare(strict_types=1);

namespace Mt2Cms\Support;

final class DayChart
{
    /**
     * @param list<int|float> $values
     * @return array{points: string, area: string, min_label: string, max_label: string}
     */
    public static function fromValues(array $values, int $width = 560, int $height = 120): array
    {
        $empty = ['points' => '', 'area' => '', 'min_label' => '', 'max_label' => ''];

        if (count($values) < 2) {
            return $empty;
        }

        $min = min($values);
        $max = max($values);
        $span = $max - $min;

        if ($span == 0.0) {
            $span = 1.0;
        }

        $padX = 4.0;
        $padY = 8.0;
        $n = count($values);
        $pts = [];

        foreach ($values as $i => $v) {
            $x = $padX + ($i / ($n - 1)) * ($width - $padX * 2);
            $y = ($height - $padY) - (((float) $v - $min) / $span) * ($height - $padY * 2);
            $pts[] = [round($x, 1), round($y, 1)];
        }

        $line = [];

        foreach ($pts as $p) {
            $line[] = $p[0] . ',' . $p[1];
        }

        $first = $pts[0];
        $last = $pts[count($pts) - 1];
        $area = $line;
        $area[] = $last[0] . ',' . ($height - 2);
        $area[] = $first[0] . ',' . ($height - 2);

        return [
            'points' => implode(' ', $line),
            'area' => implode(' ', $area),
            'min_label' => number_format((float) $min),
            'max_label' => number_format((float) $max),
        ];
    }
}
