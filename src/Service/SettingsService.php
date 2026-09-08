<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Model\Env;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Setup\ThemeCatalog;

class SettingsService
{
    public function __construct(
        private SettingsRepository $settings,
        private ThemeCatalog $themes,
    ) {
    }

    public function registrationEnabled(): bool
    {
        $value = $this->settings->get('registration_enabled');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setRegistrationEnabled(bool $enabled): void
    {
        $this->settings->set('registration_enabled', $enabled ? '1' : '0');
    }

    public function activeTheme(): string
    {
        $fromSettings = $this->settings->get('active_theme');
        $fallback = (string) (Env::getInstance()->get('THEME', 'default') ?: 'default');

        $theme = $fromSettings !== null && $fromSettings !== '' ? $fromSettings : $fallback;

        if (!$this->themes->isValid($theme)) {
            return 'default';
        }

        return $theme;
    }

    /**
     * @return list<string>
     */
    public function availableThemes(): array
    {
        $fromSettings = $this->settings->getJson('available_themes', []);
        $enabled = [];

        foreach ($fromSettings as $theme) {
            if (is_string($theme) && $this->themes->isValid($theme)) {
                $enabled[] = $theme;
            }
        }

        if ($enabled !== []) {
            return $enabled;
        }

        $active = $this->activeTheme();

        return $this->themes->isValid($active) ? [$active] : ['default'];
    }

    /**
     * @param list<string> $themes
     */
    public function setAvailableThemes(array $themes, string $activeTheme): void
    {
        $valid = [];

        foreach ($themes as $theme) {
            if (is_string($theme) && $this->themes->isValid($theme)) {
                $valid[] = $theme;
            }
        }

        if ($valid === []) {
            throw new \InvalidArgumentException('admin.themes_required');
        }

        if (!in_array($activeTheme, $valid, true)) {
            throw new \InvalidArgumentException('admin.active_theme_invalid');
        }

        $this->settings->setJson('available_themes', array_values(array_unique($valid)));
        $this->settings->set('active_theme', $activeTheme);
    }

    public function defaultLocale(): string
    {
        $fromSettings = $this->settings->get('default_locale');
        $fallback = (string) (Env::getInstance()->get('LOCALE', 'en') ?: 'en');

        return $fromSettings !== null && $fromSettings !== '' ? $fromSettings : $fallback;
    }

    public function setDefaultLocale(string $locale): void
    {
        $this->settings->set('default_locale', $locale);
    }
}
