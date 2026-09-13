<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Display;
use Mt2Cms\Game\Jobs;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;

class PlayerCensusService
{
    /** @var array<int, string> */
    private const EMPIRE_COLORS = [
        0 => '#94a3b8',
        1 => '#dc2626',
        2 => '#eab308',
        3 => '#2563eb',
    ];

    /** @var array<int, string> */
    private const JOB_COLORS = [
        0 => '#b45309',
        1 => '#7c3aed',
        2 => '#be123c',
        3 => '#0d9488',
        8 => '#ca8a04',
    ];

    /** @var array<int, string> */
    private const SKILL_COLORS = [
        0 => '#94a3b8',
        1 => '#4f46e5',
        2 => '#059669',
    ];

    /** @var list<int> */
    private const EMPIRE_ORDER = [1, 2, 3, 0];

    /** @var list<int> */
    private const CLASS_ORDER = [0, 1, 2, 3, 8];

    public function __construct(
        private AccountRepository $accounts,
        private PlayerRepository $players,
        private Display $display,
        private Translator $translator,
    ) {
    }

    /**
     * @return array{account_count: int, character_count: int}
     */
    public function totals(): array
    {
        return [
            'account_count' => $this->accounts->countAll(),
            'character_count' => $this->players->countAll(),
        ];
    }

    /**
     * @return array{
     *   account_count: int,
     *   character_count: int,
     *   empires: array{labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>},
     *   races_by_empire: list<array{empire: int, title: string, labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}>,
     *   skills_by_class: list<array{job: int, title: string, labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}>
     * }
     */
    public function snapshot(): array
    {
        $totals = $this->totals();

        return [
            'account_count' => $totals['account_count'],
            'character_count' => $totals['character_count'],
            'empires' => $this->empireSlices($this->players->countAccountsByEmpire()),
            'races_by_empire' => $this->raceByEmpireCharts($this->players->countJobsByEmpire()),
            'skills_by_class' => $this->skillByClassCharts($this->players->countSkillGroupsByJob()),
        ];
    }

    /**
     * @param list<array{empire?: int|string, n?: int|string}> $rows
     * @return array{labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}
     */
    public function empireSlices(array $rows): array
    {
        return $this->chartFromCounts(
            $this->sumByKey($rows, 'empire', self::EMPIRE_ORDER),
            fn (int $empire): string => $this->display->empire($empire),
            self::EMPIRE_COLORS,
        );
    }

    /**
     * @param list<array{empire?: int|string, job?: int|string, n?: int|string}> $rows
     * @return list<array{empire: int, title: string, labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}>
     */
    public function raceByEmpireCharts(array $rows): array
    {
        $byEmpire = [];

        foreach ($rows as $row) {
            $empire = (int) ($row['empire'] ?? 0);

            if ($empire < 1 || $empire > 3) {
                continue;
            }

            $n = (int) ($row['n'] ?? 0);

            if ($n < 1) {
                continue;
            }

            $classKey = (int) Jobs::localeJobKey((int) ($row['job'] ?? 0));
            $byEmpire[$empire][$classKey] = ($byEmpire[$empire][$classKey] ?? 0) + $n;
        }

        $charts = [];

        foreach ([1, 2, 3] as $empire) {
            if (!isset($byEmpire[$empire])) {
                continue;
            }

            $charts[] = array_merge(
                [
                    'empire' => $empire,
                    'title' => $this->display->empire($empire),
                ],
                $this->chartFromCounts(
                    $this->orderKeys($byEmpire[$empire], self::CLASS_ORDER),
                    fn (int $job): string => $this->classLabel($job),
                    self::JOB_COLORS,
                ),
            );
        }

        return $charts;
    }

    /**
     * @param list<array{job?: int|string, skill_group?: int|string, n?: int|string}> $rows
     * @return list<array{job: int, title: string, labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}>
     */
    public function skillByClassCharts(array $rows): array
    {
        $byClass = [];

        foreach ($rows as $row) {
            $n = (int) ($row['n'] ?? 0);

            if ($n < 1) {
                continue;
            }

            $classKey = (int) Jobs::localeJobKey((int) ($row['job'] ?? 0));
            $group = (int) ($row['skill_group'] ?? 0);
            $byClass[$classKey][$group] = ($byClass[$classKey][$group] ?? 0) + $n;
        }

        $charts = [];

        foreach (self::CLASS_ORDER as $job) {
            if (!isset($byClass[$job])) {
                continue;
            }

            $charts[] = array_merge(
                [
                    'job' => $job,
                    'title' => $this->classLabel($job),
                ],
                $this->chartFromCounts(
                    $this->orderKeys($byClass[$job], [0, 1, 2]),
                    fn (int $group): string => $this->skillLabel($job, $group),
                    self::SKILL_COLORS,
                ),
            );
            unset($byClass[$job]);
        }

        ksort($byClass);

        foreach ($byClass as $job => $groups) {
            $charts[] = array_merge(
                [
                    'job' => (int) $job,
                    'title' => $this->classLabel((int) $job),
                ],
                $this->chartFromCounts(
                    $this->orderKeys($groups, [0, 1, 2]),
                    fn (int $group): string => $this->skillLabel((int) $job, $group),
                    self::SKILL_COLORS,
                ),
            );
        }

        return $charts;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $preferred
     * @return array<int, int>
     */
    private function sumByKey(array $rows, string $key, array $preferred): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $n = (int) ($row['n'] ?? 0);

            if ($n < 1) {
                continue;
            }

            $id = (int) ($row[$key] ?? 0);
            $counts[$id] = ($counts[$id] ?? 0) + $n;
        }

        return $this->orderKeys($counts, $preferred);
    }

    /**
     * @param array<int, int> $counts
     * @param list<int> $preferred
     * @return array<int, int>
     */
    private function orderKeys(array $counts, array $preferred): array
    {
        $ordered = [];

        foreach ($preferred as $id) {
            if (isset($counts[$id])) {
                $ordered[$id] = $counts[$id];
                unset($counts[$id]);
            }
        }

        ksort($counts);

        foreach ($counts as $id => $n) {
            $ordered[$id] = $n;
        }

        return $ordered;
    }

    /**
     * @param array<int, int> $counts
     * @param callable(int): string $label
     * @param array<int, string> $colors
     * @return array{labels: list<string>, values: list<int>, percents: list<float>, colors: list<string>}
     */
    private function chartFromCounts(array $counts, callable $label, array $colors): array
    {
        $total = array_sum($counts);
        $labels = [];
        $values = [];
        $percents = [];
        $sliceColors = [];

        foreach ($counts as $key => $n) {
            $labels[] = $label((int) $key);
            $values[] = $n;
            $percents[] = $total > 0 ? round(100 * $n / $total, 1) : 0.0;
            $sliceColors[] = $colors[(int) $key] ?? '#64748b';
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'percents' => $percents,
            'colors' => $sliceColors,
        ];
    }

    private function classLabel(int $job): string
    {
        $key = 'job.' . $job;

        return $this->translator->has($key)
            ? $this->translator->get($key)
            : $this->translator->get('job.unknown');
    }

    private function skillLabel(int $job, int $group): string
    {
        if ($group < 1) {
            return $this->translator->get('job.skill.none');
        }

        $label = $this->display->skill($job, $group);

        return $label !== '' ? $label : $this->translator->get('job.skill.none');
    }
}
