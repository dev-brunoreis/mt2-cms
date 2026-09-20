<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Http\Controller\Admin;

use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Controller\Admin\AdminLocaleController;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Theme\ThemeEngine;
use PHPUnit\Framework\TestCase;

final class AdminLocaleControllerTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = sys_get_temp_dir() . '/mt2-admin-locale-' . bin2hex(random_bytes(4));
        mkdir($this->langDir, 0777, true);
        file_put_contents(
            $this->langDir . '/en.json',
            json_encode(['locale' => ['name' => 'English']], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->langDir . '/xx.json',
            json_encode(['locale' => ['name' => 'Extra']], JSON_THROW_ON_ERROR),
        );

        $_SESSION = [];
        $_POST = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_COOKIE = [];
        @unlink($this->langDir . '/en.json');
        @unlink($this->langDir . '/xx.json');
        @rmdir($this->langDir);
    }

    public function testUpdateSetsCookieAndKeepsAdminRedirect(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();
        $_POST = [
            '_csrf' => $token,
            'locale' => 'xx',
            'redirect' => '/admin/settings?tab=locale',
        ];

        $response = $this->controller($csrf)->update();

        self::assertSame(302, (new \ReflectionClass($response))->getProperty('status')->getValue($response));
        $headers = (new \ReflectionClass($response))->getProperty('headers')->getValue($response);
        self::assertSame('/admin/settings?tab=locale', $headers['Location'] ?? null);
        self::assertStringContainsString('locale=xx', (string) ($headers['Set-Cookie'] ?? ''));
    }

    public function testUpdateRejectsNonAdminRedirect(): void
    {
        $csrf = new Csrf();
        $_POST = [
            '_csrf' => $csrf->token(),
            'locale' => 'xx',
            'redirect' => '/account',
        ];

        $response = $this->controller($csrf)->update();
        $headers = (new \ReflectionClass($response))->getProperty('headers')->getValue($response);

        self::assertSame('/admin', $headers['Location'] ?? null);
        self::assertStringContainsString('locale=xx', (string) ($headers['Set-Cookie'] ?? ''));
    }

    private function controller(Csrf $csrf): AdminLocaleController
    {
        $theme = $this->createMock(ThemeEngine::class);
        $auth = $this->createMock(Auth::class);
        $translator = new Translator($this->langDir, 'en');

        return new AdminLocaleController(
            $theme,
            $auth,
            $csrf,
            $translator,
            new Locales($this->langDir),
        );
    }
}
