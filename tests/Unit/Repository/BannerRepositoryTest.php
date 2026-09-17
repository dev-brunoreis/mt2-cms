<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Repository;

use Mt2Cms\Repository\BannerRepository;
use PHPUnit\Framework\TestCase;

final class BannerRepositoryTest extends TestCase
{
    public function testPreviewUrlPrefersFallbackThenJpg960ThenOriginal(): void
    {
        self::assertSame(
            '/fallback.webp',
            BannerRepository::previewUrl(['fallback' => '/fallback.webp', '960' => ['jpg' => '/960.jpg']], '/orig.jpg'),
        );
        self::assertSame(
            '/960.jpg',
            BannerRepository::previewUrl(['960' => ['jpg' => '/960.jpg']], '/orig.jpg'),
        );
        self::assertSame('/orig.jpg', BannerRepository::previewUrl([], '/orig.jpg'));
        self::assertSame('', BannerRepository::previewUrl([], ''));
    }

    public function testFullUrlPrefersOriginalPath(): void
    {
        self::assertSame(
            '/orig.jpg',
            BannerRepository::fullUrl(['fallback' => '/fallback.webp'], '/orig.jpg'),
        );
        self::assertSame(
            '/fallback.webp',
            BannerRepository::fullUrl(['fallback' => '/fallback.webp'], ''),
        );
        self::assertSame('', BannerRepository::fullUrl([], ''));
    }
}
