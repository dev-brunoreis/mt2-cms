<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\LocaleJsonTree;
use PHPUnit\Framework\TestCase;

/**
 * Shipped locale packs must stay in sync with lang/en.json (keys + placeholders).
 */
final class ShippedLocalesTest extends TestCase
{
    /** Currently shipped: English only. */
    private const SHIPPED = ['en'];

    /** @var array<string, string> */
    private const NAMES = [
        'en' => 'English',
    ];

    public function testShippedLocalesMatchEnglishKeysAndPlaceholders(): void
    {
        $en = $this->load('en');
        $enPaths = LocaleJsonTree::leafPaths($en);
        $enPlaceholders = LocaleJsonTree::placeholdersInTree($en);

        self::assertNotSame([], $enPaths);

        foreach (self::SHIPPED as $code) {
            $path = BASE_DIR . '/lang/' . $code . '.json';
            self::assertFileExists($path, "Missing locale file for {$code}");

            $data = $this->load($code);
            self::assertSame(
                self::NAMES[$code],
                $data['locale']['name'] ?? null,
                "locale.name for {$code}",
            );

            self::assertSame(
                $enPaths,
                LocaleJsonTree::leafPaths($data),
                "Leaf key paths must match en for {$code}",
            );

            self::assertSame(
                $enPlaceholders,
                LocaleJsonTree::placeholdersInTree($data),
                "Placeholders must match en for {$code}",
            );
        }
    }

    public function testOnlyEnglishLocaleFileIsShipped(): void
    {
        $files = glob(BASE_DIR . '/lang/*.json');
        self::assertIsArray($files);
        $codes = array_map(
            static fn (string $file): string => basename($file, '.json'),
            $files,
        );
        sort($codes);

        self::assertSame(['en'], $codes);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $code): array
    {
        $raw = file_get_contents(BASE_DIR . '/lang/' . $code . '.json');
        self::assertNotFalse($raw);
        $data = json_decode($raw, true);
        self::assertIsArray($data);

        return $data;
    }
}
