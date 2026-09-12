<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

/**
 * `player.job` is the Metin2 race id (`MAIN_RACE_*`), not the class id (`JOB_*`).
 *
 * Default ninja and shaman are female; the male variants are races 5 and 7.
 */
class Jobs
{
    /** @var array<int, array{job: int, sex: string}> */
    private const RACES = [
        0 => ['job' => 0, 'sex' => 'male'],
        1 => ['job' => 1, 'sex' => 'female'],
        2 => ['job' => 2, 'sex' => 'male'],
        3 => ['job' => 3, 'sex' => 'female'],
        4 => ['job' => 0, 'sex' => 'female'],
        5 => ['job' => 1, 'sex' => 'male'],
        6 => ['job' => 2, 'sex' => 'female'],
        7 => ['job' => 3, 'sex' => 'male'],
        8 => ['job' => 8, 'sex' => 'male'],
    ];

    /**
     * @return list<int>
     */
    public static function raceIds(): array
    {
        return array_keys(self::RACES);
    }

    public static function localeJobKey(int $race): string
    {
        return (string) (self::RACES[$race]['job'] ?? $race);
    }

    public static function sex(int $race): ?string
    {
        return self::RACES[$race]['sex'] ?? null;
    }
}
