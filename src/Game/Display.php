<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

class Display
{
    public static function job(mixed $job): string
    {
        return match ((int) $job) {
            0 => 'Guerreiro',
            1 => 'Ninja',
            2 => 'Sura',
            3 => 'Shaman',
            4 => 'Guerreira',
            5 => 'Ninja',
            6 => 'Sura',
            7 => 'Shaman',
            8 => 'Lycan',
            default => 'Desconhecido',
        };
    }

    public static function empire(mixed $empire): string
    {
        return match ((int) $empire) {
            1 => 'Reino Vermelho',
            2 => 'Reino Amarelo',
            3 => 'Reino Azul',
            default => 'Nenhum',
        };
    }

    /**
     * Playtime is stored in minutes on the player table.
     */
    public static function playtime(mixed $minutes): string
    {
        $total = max(0, (int) $minutes);
        $days = intdiv($total, 1440);
        $hours = intdiv($total % 1440, 60);
        $mins = $total % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . 'd';
        }

        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . 'h';
        }

        $parts[] = $mins . 'm';

        return implode(' ', $parts);
    }

    /**
     * MySQL DATETIME → relative time in pt-BR, with calendar date.
     */
    public static function datetime(mixed $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return 'Nunca';
        }

        try {
            $at = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return 'Nunca';
        }

        $now = new \DateTimeImmutable('now');
        $diff = $now->getTimestamp() - $at->getTimestamp();
        $calendar = $at->format('d/m/Y H:i');

        if ($diff < 0) {
            return $calendar;
        }

        if ($diff < 60) {
            $relative = 'agora';
        } elseif ($diff < 3600) {
            $n = intdiv($diff, 60);
            $relative = 'há ' . $n . ' min';
        } elseif ($diff < 86400) {
            $n = intdiv($diff, 3600);
            $relative = 'há ' . $n . 'h';
        } elseif ($diff < 2592000) {
            $n = intdiv($diff, 86400);
            $relative = 'há ' . $n . ' dia' . ($n === 1 ? '' : 's');
        } elseif ($diff < 31536000) {
            $n = intdiv($diff, 2592000);
            $relative = 'há ' . $n . ' ' . ($n === 1 ? 'mês' : 'meses');
        } else {
            $n = intdiv($diff, 31536000);
            $relative = 'há ' . $n . ' ano' . ($n === 1 ? '' : 's');
        }

        return $relative . ' · ' . $calendar;
    }
}
