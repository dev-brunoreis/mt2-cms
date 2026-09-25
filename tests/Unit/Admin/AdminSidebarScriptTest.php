<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

final class AdminSidebarScriptTest extends TestCase
{
    public function testSidebarScriptPersistsNavScrollWithoutJumpingToActiveItem(): void
    {
        $script = (string) file_get_contents(BASE_DIR . '/public/js/admin/admin-sidebar.js');
        $layout = (string) file_get_contents(BASE_DIR . '/themes/admin/templates/layouts/panel.twig');

        self::assertStringContainsString('data-admin-sidebar-nav', $layout);
        self::assertMatchesRegularExpression(
            '/action="\/admin\/logout"[\s\S]*admin-sidebar\.js[\s\S]*<\/aside>/',
            $layout,
        );
        self::assertStringContainsString('mt2cms.admin.sidebarNavScroll', $script);
        self::assertStringContainsString('sessionStorage.getItem', $script);
        self::assertStringContainsString('sessionStorage.setItem', $script);
        self::assertStringContainsString('nav.scrollTop = saved', $script);
        self::assertStringContainsString("addEventListener('pointerdown'", $script);
        self::assertStringContainsString('requestAnimationFrame', $script);
        self::assertStringNotContainsString("nav.addEventListener('scroll'", $script);
        self::assertStringNotContainsString('scrollIntoView', $script);
    }
}
