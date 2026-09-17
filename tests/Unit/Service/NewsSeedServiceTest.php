<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Service\NewsSeedService;
use Mt2Cms\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class NewsSeedServiceTest extends TestCase
{
    public function testSeedsPublishedWelcomePostForFirstAdmin(): void
    {
        $news = $this->createMock(NewsRepository::class);
        $admins = $this->createMock(AdminRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(NewsSeedService::SETTING_KEY)->willReturn(null);
        $news->method('countAll')->willReturn(0);
        $admins->method('first')->willReturn(['id' => 1, 'login' => 'founder']);

        $news->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $data): bool {
                self::assertSame(NewsSeedService::TITLE, $data['title']);
                self::assertSame(1, $data['author_admin_id']);
                self::assertSame('founder', $data['author_login']);
                self::assertSame('published', $data['status']);
                self::assertTrue($data['comments_enabled']);
                self::assertNull($data['cover_image']);
                self::assertStringContainsString('<p>', $data['body']);
                self::assertStringContainsString('three kingdoms', $data['body']);
                self::assertStringContainsString('Getting started', $data['body']);

                return true;
            }))
            ->willReturn(1);

        $settings->expects(self::once())
            ->method('set')
            ->with(NewsSeedService::SETTING_KEY, '1');

        $this->service($news, $admins, $settings)->seedIfNeeded();
    }

    public function testSkipsWhenAlreadySeeded(): void
    {
        $news = $this->createMock(NewsRepository::class);
        $admins = $this->createMock(AdminRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(NewsSeedService::SETTING_KEY)->willReturn('1');
        $news->expects(self::never())->method('countAll');
        $news->expects(self::never())->method('create');
        $settings->expects(self::never())->method('set');

        $this->service($news, $admins, $settings)->seedIfNeeded();
    }

    public function testMarksSeededWithoutInsertWhenNewsAlreadyExist(): void
    {
        $news = $this->createMock(NewsRepository::class);
        $admins = $this->createMock(AdminRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(NewsSeedService::SETTING_KEY)->willReturn(null);
        $news->method('countAll')->willReturn(3);
        $news->expects(self::never())->method('create');
        $admins->expects(self::never())->method('first');
        $settings->expects(self::once())
            ->method('set')
            ->with(NewsSeedService::SETTING_KEY, '1');

        $this->service($news, $admins, $settings)->seedIfNeeded();
    }

    public function testWaitsWhenNoAdminExistsYet(): void
    {
        $news = $this->createMock(NewsRepository::class);
        $admins = $this->createMock(AdminRepository::class);
        $settings = $this->createMock(SettingsRepository::class);

        $settings->method('get')->with(NewsSeedService::SETTING_KEY)->willReturn(null);
        $news->method('countAll')->willReturn(0);
        $admins->method('first')->willReturn(null);
        $news->expects(self::never())->method('create');
        $settings->expects(self::never())->method('set');

        $this->service($news, $admins, $settings)->seedIfNeeded();
    }

    private function service(
        NewsRepository $news,
        AdminRepository $admins,
        SettingsRepository $settings,
    ): NewsSeedService {
        return new NewsSeedService($news, $admins, $settings, new HtmlSanitizer());
    }
}
