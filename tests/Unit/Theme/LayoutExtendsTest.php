<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Theme;

use Mt2Cms\Theme\LayoutMerger;
use Mt2Cms\Theme\ThemeResolver;
use PHPUnit\Framework\TestCase;

final class LayoutExtendsTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/mt2cms-theme-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/default/layouts', 0777, true);
        mkdir($this->tmp . '/child/layouts', 0777, true);

        file_put_contents($this->tmp . '/default/theme.json', '{"name":"default","parent":null}');
        file_put_contents($this->tmp . '/child/theme.json', '{"name":"child","parent":"default"}');
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->tmp);
    }

    public function testExtendsMergesMainOntoShell(): void
    {
        file_put_contents($this->tmp . '/default/layouts/_shell.json', json_encode([
            'id' => 'root',
            'template' => 'layouts/shell.twig',
            'slots' => [
                'header' => [['id' => 'navbar', 'template' => 'components/navbar.twig']],
                'main' => [],
                'footer' => [['id' => 'footer', 'template' => 'components/footer.twig']],
            ],
        ]));
        file_put_contents($this->tmp . '/default/layouts/home.json', json_encode([
            'extends' => '_shell',
            'slots' => [
                'main' => [['id' => 'content', 'template' => 'pages/home.twig']],
            ],
        ]));

        $layout = (new ThemeResolver($this->tmp, 'default'))->resolveLayout('home');

        self::assertSame('layouts/shell.twig', $layout['template']);
        self::assertArrayNotHasKey('extends', $layout);
        self::assertSame('pages/home.twig', $layout['slots']['main'][0]['template']);
        self::assertSame('navbar', $layout['slots']['header'][0]['id']);
    }

    public function testCircularExtendsThrows(): void
    {
        file_put_contents($this->tmp . '/default/layouts/a.json', '{"extends":"b","slots":{}}');
        file_put_contents($this->tmp . '/default/layouts/b.json', '{"extends":"a","slots":{}}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular layout extends');
        (new ThemeResolver($this->tmp, 'default'))->resolveLayout('a');
    }

    public function testChildThemeRemovesWidgetById(): void
    {
        file_put_contents($this->tmp . '/default/layouts/_shell.json', json_encode([
            'id' => 'root',
            'template' => 'layouts/shell.twig',
            'slots' => [
                'right' => [
                    ['id' => 'widget-ranking', 'template' => 'components/widget-ranking.twig'],
                    ['id' => 'widget-discord', 'template' => 'components/widget-discord.twig'],
                ],
            ],
        ]));
        file_put_contents($this->tmp . '/child/layouts/_shell.json', json_encode([
            'slots' => [
                'right' => [
                    ['id' => 'widget-discord', 'remove' => true],
                ],
            ],
        ]));
        file_put_contents($this->tmp . '/default/layouts/home.json', json_encode([
            'extends' => '_shell',
            'slots' => [
                'main' => [['id' => 'content', 'template' => 'pages/home.twig']],
            ],
        ]));

        $layout = (new ThemeResolver($this->tmp, 'child'))->resolveLayout('home');
        $ids = array_map(static fn (array $n): string => (string) $n['id'], $layout['slots']['right']);

        self::assertSame(['widget-ranking'], $ids);
    }

    public function testMergeByIdRemoveFlag(): void
    {
        $base = [
            'slots' => [
                'right' => [
                    ['id' => 'a', 'template' => 'a.twig'],
                    ['id' => 'b', 'template' => 'b.twig'],
                ],
            ],
        ];
        $overlay = [
            'slots' => [
                'right' => [
                    ['id' => 'b', 'remove' => true],
                ],
            ],
        ];

        $merged = LayoutMerger::mergeById($base, $overlay);
        self::assertCount(1, $merged['slots']['right']);
        self::assertSame('a', $merged['slots']['right'][0]['id']);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }

        rmdir($dir);
    }
}
