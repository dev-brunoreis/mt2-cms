<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Game\Display;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\PlayerCensusService;
use PHPUnit\Framework\TestCase;

final class PlayerCensusServiceTest extends TestCase
{
    public function testEmpireSlicesOrderAndSkipZeros(): void
    {
        $chart = $this->service()->empireSlices([
            ['empire' => 2, 'n' => 10],
            ['empire' => 0, 'n' => 5],
            ['empire' => 1, 'n' => 25],
            ['empire' => 3, 'n' => 0],
        ]);

        self::assertSame(['Red Empire', 'Yellow Empire', 'None'], $chart['labels']);
        self::assertSame([25, 10, 5], $chart['values']);
        self::assertSame([62.5, 25.0, 12.5], $chart['percents']);
        self::assertSame(['#dc2626', '#eab308', '#94a3b8'], $chart['colors']);
    }

    public function testRaceByEmpireCollapsesSexVariantsAndSkipsEmptyKingdoms(): void
    {
        $charts = $this->service()->raceByEmpireCharts([
            ['empire' => 1, 'job' => 0, 'n' => 4],
            ['empire' => 1, 'job' => 4, 'n' => 6],
            ['empire' => 1, 'job' => 1, 'n' => 2],
            ['empire' => 0, 'job' => 2, 'n' => 9],
            ['empire' => 2, 'job' => 8, 'n' => 3],
        ]);

        self::assertCount(2, $charts);
        self::assertSame(1, $charts[0]['empire']);
        self::assertSame('Red Empire', $charts[0]['title']);
        self::assertSame(['Warrior', 'Ninja'], $charts[0]['labels']);
        self::assertSame([10, 2], $charts[0]['values']);
        self::assertSame([83.3, 16.7], $charts[0]['percents']);

        self::assertSame(2, $charts[1]['empire']);
        self::assertSame(['Lycan'], $charts[1]['labels']);
        self::assertSame([3], $charts[1]['values']);
        self::assertSame([100.0], $charts[1]['percents']);
    }

    public function testSkillByClassUsesPathLabelsAndNoPath(): void
    {
        $charts = $this->service()->skillByClassCharts([
            ['job' => 0, 'skill_group' => 0, 'n' => 5],
            ['job' => 4, 'skill_group' => 1, 'n' => 3],
            ['job' => 0, 'skill_group' => 2, 'n' => 2],
            ['job' => 8, 'skill_group' => 1, 'n' => 1],
        ]);

        self::assertCount(2, $charts);
        self::assertSame('Warrior', $charts[0]['title']);
        self::assertSame(['No path', 'Arahan', 'Partizan'], $charts[0]['labels']);
        self::assertSame([5, 3, 2], $charts[0]['values']);
        self::assertSame([50.0, 30.0, 20.0], $charts[0]['percents']);

        self::assertSame('Lycan', $charts[1]['title']);
        self::assertSame(['Instinct'], $charts[1]['labels']);
        self::assertSame([1], $charts[1]['values']);
    }

    public function testSkillByClassOmitsClassesWithNoCharacters(): void
    {
        $charts = $this->service()->skillByClassCharts([
            ['job' => 3, 'skill_group' => 1, 'n' => 2],
            ['job' => 2, 'skill_group' => 0, 'n' => 0],
        ]);

        self::assertCount(1, $charts);
        self::assertSame('Shaman', $charts[0]['title']);
        self::assertSame(['Dragon'], $charts[0]['labels']);
    }

    private function service(): PlayerCensusService
    {
        $translator = new Translator(BASE_DIR . '/lang', 'en', 'en');

        return new PlayerCensusService(
            $this->createMock(AccountRepository::class),
            $this->createMock(PlayerRepository::class),
            new Display($translator),
            $translator,
        );
    }
}
