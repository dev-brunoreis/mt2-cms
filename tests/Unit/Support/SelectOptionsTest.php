<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\SelectOptions;
use PHPUnit\Framework\TestCase;

final class SelectOptionsTest extends TestCase
{
    public function testSortByOrdersItemsByLabelCaseInsensitively(): void
    {
        $sorted = SelectOptions::sortBy([
            ['value' => 'b', 'label' => 'sword'],
            ['value' => 'a', 'label' => 'Armor'],
            ['value' => 'c', 'label' => 'bow'],
        ]);

        self::assertSame(['a', 'c', 'b'], array_column($sorted, 'value'));
    }

    public function testSortMapKeepsKeys(): void
    {
        $sorted = SelectOptions::sortMap([
            'published' => 'Published',
            'draft' => 'Draft',
        ]);

        self::assertSame(['draft', 'published'], array_keys($sorted));
        self::assertSame('Draft', $sorted['draft']);
    }
}
