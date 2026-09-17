<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Support\HtmlSanitizer;

/**
 * Seeds one published welcome post once (empty table + flag), authored by the first admin.
 */
class NewsSeedService
{
    public const SETTING_KEY = 'news_welcome_seeded';

    public const TITLE = 'Welcome to the realm';

    public function __construct(
        private NewsRepository $news,
        private AdminRepository $admins,
        private SettingsRepository $settings,
        private HtmlSanitizer $sanitizer,
    ) {
    }

    public function seedIfNeeded(): void
    {
        if ($this->settings->get(self::SETTING_KEY) === '1') {
            return;
        }

        if ($this->news->countAll() > 0) {
            $this->settings->set(self::SETTING_KEY, '1');

            return;
        }

        $admin = $this->admins->first();

        if ($admin === null) {
            return;
        }

        $this->news->create([
            'title' => self::TITLE,
            'body' => $this->sanitizer->sanitize($this->welcomeBody()),
            'cover_image' => null,
            'author_admin_id' => (int) $admin['id'],
            'author_login' => (string) $admin['login'],
            'status' => 'published',
            'comments_enabled' => true,
            'seo_title' => null,
            'seo_description' => null,
            'seo_og_image' => null,
        ]);

        $this->settings->set(self::SETTING_KEY, '1');
    }

    private function welcomeBody(): string
    {
        return <<<'HTML'
<p>The three kingdoms are waiting. Create an account, download the client, and begin your journey.</p>
<h3>Getting started</h3>
<ul>
<li>Register an account from the site and log in</li>
<li>Download the game client</li>
<li>Join the community on Discord</li>
</ul>
<p>Staff will post updates, events, and maintenance notices here. See you in-game.</p>
HTML;
    }
}
