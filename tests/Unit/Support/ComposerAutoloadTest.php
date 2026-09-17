<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\ComposerAutoload;
use PHPUnit\Framework\TestCase;

final class ComposerAutoloadTest extends TestCase
{
    private string $tmpDir = '';

    protected function tearDown(): void
    {
        unset($GLOBALS['mt2cms_autoload_loaded']);

        if ($this->tmpDir !== '' && is_dir($this->tmpDir)) {
            $autoload = $this->tmpDir . '/vendor/autoload.php';
            if (is_file($autoload)) {
                unlink($autoload);
            }
            $vendor = $this->tmpDir . '/vendor';
            if (is_dir($vendor)) {
                rmdir($vendor);
            }
            rmdir($this->tmpDir);
            $this->tmpDir = '';
        }
    }

    public function testExistsIsFalseWhenVendorAutoloadMissing(): void
    {
        self::assertFalse(ComposerAutoload::exists(sys_get_temp_dir() . '/mt2-cms-missing-vendor'));
    }

    public function testLoadRequiresVendorAutoloadWhenPresent(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/mt2-cms-autoload-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/vendor', 0777, true);
        file_put_contents(
            $this->tmpDir . '/vendor/autoload.php',
            '<?php $GLOBALS["mt2cms_autoload_loaded"] = true;',
        );

        ComposerAutoload::load($this->tmpDir);

        self::assertTrue($GLOBALS['mt2cms_autoload_loaded'] ?? false);
    }

    public function testHtmlPageTellsOperatorToRunComposerInstall(): void
    {
        $html = ComposerAutoload::html();

        self::assertStringContainsString('Dependencies not installed', $html);
        self::assertStringContainsString('composer install', $html);
        self::assertStringNotContainsString('RuntimeException', $html);
        self::assertStringNotContainsString('stack', $html);
    }

    public function testHttpResponseIsUnavailableWithSecurityHeaders(): void
    {
        $headers = ComposerAutoload::httpHeaders();

        self::assertSame('text/html; charset=UTF-8', $headers['Content-Type']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame('DENY', $headers['X-Frame-Options']);
        self::assertSame('no-store', $headers['Cache-Control']);
    }

    public function testCliMessageTellsOperatorToRunComposerInstall(): void
    {
        self::assertSame(
            "Dependencies are not installed. Run: composer install\n",
            ComposerAutoload::cliMessage(),
        );
    }

    public function testFrontControllersLoadAutoloadWithoutThrowing(): void
    {
        $files = [
            'public/index.php',
            'bin/migrate.php',
            'bin/payments-process.php',
            'bin/economy-tick.php',
            'bin/economy-seed-demo.php',
        ];

        foreach ($files as $relative) {
            $source = (string) file_get_contents(BASE_DIR . '/' . $relative);

            self::assertStringContainsString(
                "require BASE_DIR . '/src/bootstrap/autoload.php'",
                $source,
                $relative . ' must load vendor via bootstrap/autoload.php',
            );
            self::assertStringNotContainsString(
                'Autoload file not found. Run composer install.',
                $source,
                $relative . ' must not throw when vendor is missing',
            );
        }
    }
}
