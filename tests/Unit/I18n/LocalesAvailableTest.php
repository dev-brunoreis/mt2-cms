<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\I18n;

use Mt2Cms\I18n\Locales;
use PHPUnit\Framework\TestCase;

final class LocalesAvailableTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mt2-locales-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/lang', 0777, true);
        mkdir($this->root . '/public/flag', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testAvailableIncludesFlagUrl(): void
    {
        $this->writeLocale('en', 'English');
        $this->writeFlag('en');

        $list = (new Locales($this->root . '/lang'))->available();

        self::assertCount(1, $list);
        self::assertSame('en', $list[0]['code']);
        self::assertSame('English', $list[0]['name']);
        self::assertSame('/flag/en.webp', $list[0]['flag']);
    }

    public function testAvailableMapsLanguageCodesToCountryFlags(): void
    {
        $this->writeLocale('cs', 'Czech');
        $this->writeLocale('da', 'Danish');
        $this->writeLocale('el', 'Greek');
        $this->writeFlag('cz');
        $this->writeFlag('dk');
        $this->writeFlag('gr');

        $list = (new Locales($this->root . '/lang'))->available();
        $flags = [];

        foreach ($list as $item) {
            $flags[$item['code']] = $item['flag'];
        }

        self::assertSame('/flag/cz.webp', $flags['cs']);
        self::assertSame('/flag/dk.webp', $flags['da']);
        self::assertSame('/flag/gr.webp', $flags['el']);
    }

    public function testAvailableReturnsNullFlagWhenMissing(): void
    {
        $this->writeLocale('xx', 'Unknown');

        $list = (new Locales($this->root . '/lang'))->available();

        self::assertCount(1, $list);
        self::assertNull($list[0]['flag']);
    }

    public function testShippedEnglishLocaleHasFlag(): void
    {
        $list = (new Locales(BASE_DIR . '/lang'))->available();
        $english = null;

        foreach ($list as $item) {
            if ($item['code'] === 'en') {
                $english = $item;
                break;
            }
        }

        self::assertNotNull($english);
        self::assertSame('/flag/en.webp', $english['flag']);
        self::assertFileExists(BASE_DIR . '/public/flag/en.webp');
    }

    public function testAvailableIncludesDirectoryLocale(): void
    {
        mkdir($this->root . '/lang/de', 0777, true);
        file_put_contents(
            $this->root . '/lang/de/locale.json',
            json_encode(['name' => 'Deutsch'], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->root . '/lang/de/nav.json',
            json_encode(['home' => 'Start'], JSON_THROW_ON_ERROR),
        );

        $list = (new Locales($this->root . '/lang'))->available();
        $codes = array_column($list, 'code');

        self::assertContains('de', $codes);
        self::assertTrue((new Locales($this->root . '/lang'))->isSupported('de'));
    }

    private function writeLocale(string $code, string $name): void
    {
        file_put_contents(
            $this->root . '/lang/' . $code . '.json',
            json_encode(['locale' => ['name' => $name]], JSON_THROW_ON_ERROR),
        );
    }

    private function writeFlag(string $code): void
    {
        file_put_contents($this->root . '/public/flag/' . $code . '.webp', 'flag');
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;

            if (is_dir($full)) {
                $this->removeDir($full);
                continue;
            }

            unlink($full);
        }

        rmdir($path);
    }
}
