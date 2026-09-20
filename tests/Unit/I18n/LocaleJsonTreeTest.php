<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\LocaleJsonTree;
use PHPUnit\Framework\TestCase;

final class LocaleJsonTreeTest extends TestCase
{
    public function testFlattenAndApplyRoundTrip(): void
    {
        $tree = [
            'nav' => ['home' => 'Home'],
            'items' => ['one' => '{n} item', 'other' => '{n} items'],
            'admin' => ['x' => 'Admin'],
        ];

        $leaves = LocaleJsonTree::flatten($tree, true);
        $paths = array_column($leaves, 'path');

        self::assertSame(['nav.home', 'items.one', 'items.other'], $paths);
        self::assertSame(4, LocaleJsonTree::countLeaves($tree));
        self::assertCount(3, $leaves);

        $applied = LocaleJsonTree::apply($tree, [
            'nav.home' => 'Início',
            'items.one' => '{n} item',
            'items.other' => '{n} itens',
        ]);

        self::assertSame('Início', $applied['nav']['home']);
        self::assertSame('{n} itens', $applied['items']['other']);
        self::assertSame('Admin', $applied['admin']['x']);
    }

    public function testProtectAndUnprotectPlaceholders(): void
    {
        self::assertSame(
            'Hello <x>{name}</x>, you have <x>{n}</x> left',
            LocaleJsonTree::protectPlaceholders('Hello {name}, you have {n} left'),
        );
        self::assertSame(
            'Branding &amp; footer',
            LocaleJsonTree::protectPlaceholders('Branding & footer'),
        );
        self::assertSame(
            'Branding & footer',
            LocaleJsonTree::unprotectPlaceholders('Branding &amp; footer'),
        );
        self::assertSame(
            'Hello {name}, you have {n} left',
            LocaleJsonTree::unprotectPlaceholders('Hello <x>{name}</x>, you have <x>{n}</x> left'),
        );
        self::assertSame(
            'Hi {name}',
            LocaleJsonTree::unprotectPlaceholders('Hi { name }'),
        );
        self::assertSame(
            'Ends {date}',
            LocaleJsonTree::sanitizeTranslation('Ends {date}', ''),
        );
        self::assertSame(
            'Ends {date}',
            LocaleJsonTree::sanitizeTranslation('Ends {date}', 'Fin'),
        );
        self::assertSame(
            'Fin {date}',
            LocaleJsonTree::sanitizeTranslation('Ends {date}', 'Fin {date}'),
        );
    }

    public function testPlaceholdersInTree(): void
    {
        $tree = ['a' => 'Hi {name}', 'b' => 'No placeholders'];

        self::assertSame(['a={name}'], LocaleJsonTree::placeholdersInTree($tree));
    }
}
