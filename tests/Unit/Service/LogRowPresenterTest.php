<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\LogRowPresenter;
use PHPUnit\Framework\TestCase;

final class LogRowPresenterTest extends TestCase
{
    public function testGoldlogShopSellSummary(): void
    {
        $presenter = new LogRowPresenter(new Translator(dirname(__DIR__, 3) . '/lang', 'en'));
        $rows = $presenter->decorate('goldlog', [[
            'date' => '2026-09-12',
            'time' => '20:00:00',
            'pid' => 42,
            'what' => 150000,
            'how' => 'SHOP_SELL',
            'hint' => 'Long Sword 1',
        ]], [42 => 'Hero']);

        self::assertStringContainsString('Hero', $rows[0]['_summary']);
        self::assertStringContainsString('Long Sword', $rows[0]['_summary']);
        self::assertStringContainsString('150,000', $rows[0]['_summary']);
        self::assertStringContainsString('player shop', $rows[0]['_summary']);
        self::assertSame('Sold in player shop', $rows[0]['_how_label']);
        self::assertSame(150000, $rows[0]['_yang']);
    }

    public function testItemLogShopSellSummary(): void
    {
        $presenter = new LogRowPresenter(new Translator(dirname(__DIR__, 3) . '/lang', 'en'));
        $rows = $presenter->decorate('log', [[
            'type' => 'ITEM',
            'time' => '2026-09-12 22:49:27',
            'who' => 2,
            'what' => 80000072,
            'how' => 'SHOP_SELL',
            'hint' => 'Lunar Sword+9 1([SA]Admin) 40 1',
            'vnum' => 229,
        ]], [2 => 'Test'], [229 => 'Lunar Sword+9']);

        self::assertStringContainsString('Test', $rows[0]['_summary']);
        self::assertStringContainsString('Lunar Sword+9', $rows[0]['_summary']);
        self::assertStringContainsString('40', $rows[0]['_summary']);
        self::assertStringContainsString('[SA]Admin', $rows[0]['_summary']);
        self::assertSame(40, $rows[0]['_yang']);
    }

    public function testNeededLookupsCollectsPlayerAndVnum(): void
    {
        $presenter = new LogRowPresenter(new Translator(dirname(__DIR__, 3) . '/lang', 'en'));
        $need = $presenter->neededLookups('cube', [[
            'pid' => 9,
            'item_vnum' => 299,
            'item_count' => 1,
            'success' => 1,
        ]]);

        self::assertSame([9], $need['players']);
        self::assertSame([299], $need['vnums']);
    }
}
