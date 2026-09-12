<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\BannerRepository;
use Mt2Cms\Repository\SettingsRepository;

/**
 * Seeds the four class banners from theme masters once (empty table + flag).
 */
class BannerSeedService
{
    /** @var list<array{file: string, title: string, alt: string, sort_order: int}> */
    private const DEFAULTS = [
        ['file' => 'warrior_banner.jpg', 'title' => 'Warrior', 'alt' => 'Warrior', 'sort_order' => 10],
        ['file' => 'ninja_banner.jpg', 'title' => 'Ninja', 'alt' => 'Ninja', 'sort_order' => 20],
        ['file' => 'shura_banner.jpg', 'title' => 'Sura', 'alt' => 'Sura', 'sort_order' => 30],
        ['file' => 'shaman_banner.jpg', 'title' => 'Shaman', 'alt' => 'Shaman', 'sort_order' => 40],
    ];

    public function __construct(
        private BannerRepository $banners,
        private BannerUploadService $uploads,
        private SettingsRepository $settings,
        private string $themeSrcDir,
    ) {
    }

    public function seedIfNeeded(): void
    {
        if ($this->settings->get('banners_seeded') === '1') {
            return;
        }

        if ($this->banners->countAll() > 0) {
            $this->settings->set('banners_seeded', '1');

            return;
        }

        if (!extension_loaded('gd')) {
            return;
        }

        $seeded = 0;

        foreach (self::DEFAULTS as $slide) {
            $source = rtrim($this->themeSrcDir, '/') . '/' . $slide['file'];

            if (!is_file($source)) {
                continue;
            }

            try {
                $stored = $this->uploads->storeFromPath($source);
                $this->banners->create([
                    'title' => $slide['title'],
                    'alt' => $slide['alt'],
                    'link_url' => null,
                    'original_path' => $stored['original_path'],
                    'variants' => $stored['variants'],
                    'sort_order' => $slide['sort_order'],
                    'enabled' => true,
                ]);
                $seeded++;
            } catch (\Throwable) {
                // Leave incomplete seed for a later boot / rebuild with GD.
                return;
            }
        }

        if ($seeded > 0) {
            $this->settings->set('banners_seeded', '1');
        }
    }
}
