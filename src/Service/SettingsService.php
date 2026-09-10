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

        if (!$this->themes->isValid($theme) || !$this->themes->isPublic($theme)) {
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
            if (is_string($theme) && $this->themes->isValid($theme) && $this->themes->isPublic($theme)) {
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
            if (is_string($theme) && $this->themes->isValid($theme) && $this->themes->isPublic($theme)) {
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

    public function newsCommentsEnabled(): bool
    {
        $value = $this->settings->get('news_comments_enabled');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setNewsCommentsEnabled(bool $enabled): void
    {
        $this->settings->set('news_comments_enabled', $enabled ? '1' : '0');
    }

    public function newsCommentsRequireApproval(): bool
    {
        $value = $this->settings->get('news_comments_require_approval');

        if ($value === null) {
            return false;
        }

        return $value === '1';
    }

    public function setNewsCommentsRequireApproval(bool $required): void
    {
        $this->settings->set('news_comments_require_approval', $required ? '1' : '0');
    }

    public function captchaPublicEnabled(): bool
    {
        $value = $this->settings->get('captcha_public');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setCaptchaPublicEnabled(bool $enabled): void
    {
        $this->settings->set('captcha_public', $enabled ? '1' : '0');
    }

    public function captchaAdminEnabled(): bool
    {
        $value = $this->settings->get('captcha_admin');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setCaptchaAdminEnabled(bool $enabled): void
    {
        $this->settings->set('captcha_admin', $enabled ? '1' : '0');
    }

    public function adminTwoFactorRequired(): bool
    {
        $value = $this->settings->get('admin_2fa_required');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setAdminTwoFactorRequired(bool $required): void
    {
        $this->settings->set('admin_2fa_required', $required ? '1' : '0');
    }

    public function requireVerifiedEmail(): bool
    {
        $value = $this->settings->get('require_verified_email');

        if ($value === null) {
            return false;
        }

        return $value === '1';
    }

    public function setRequireVerifiedEmail(bool $required): void
    {
        $this->settings->set('require_verified_email', $required ? '1' : '0');
    }

    public function mailFromAddress(): string
    {
        $value = trim((string) ($this->settings->get('mail_from_address') ?? ''));

        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }

        return 'noreply@localhost';
    }

    public function mailFromName(): string
    {
        $value = trim((string) ($this->settings->get('mail_from_name') ?? ''));

        return $value !== '' ? $value : 'Mt2 CMS';
    }

    public function setMailFrom(string $address, string $name): void
    {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('settings.invalid_mail_from');
        }

        $this->settings->set('mail_from_address', $address);
        $this->settings->set('mail_from_name', trim($name));
    }

    public function mailSubjectPrefix(): string
    {
        $name = $this->mailFromName();

        return '[' . $name . '] ';
    }

    public function siteUrl(): string
    {
        $fromSettings = trim((string) ($this->settings->get('site_url') ?? ''));

        if ($fromSettings !== '') {
            return rtrim($fromSettings, '/');
        }

        $fromEnv = trim((string) (Env::getInstance()->get('APP_URL') ?? ''));

        if ($fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    public function setSiteUrl(string $url): void
    {
        $url = rtrim(trim($url), '/');

        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('settings.invalid_site_url');
        }

        $this->settings->set('site_url', $url);
    }

    public function onlineWindowMinutes(): int
    {
        $value = (int) ($this->settings->get('online_window_minutes') ?? 15);

        return max(1, min(120, $value));
    }

    public function setOnlineWindowMinutes(int $minutes): void
    {
        $this->settings->set('online_window_minutes', (string) max(1, min(120, $minutes)));
    }

    public function paypalMode(): string
    {
        $value = strtolower(trim((string) ($this->settings->get('paypal_mode') ?? 'sandbox')));

        return $value === 'live' ? 'live' : 'sandbox';
    }

    public function setPaypalMode(string $mode): void
    {
        $this->settings->set('paypal_mode', $mode === 'live' ? 'live' : 'sandbox');
    }

    public function paypalCurrency(): string
    {
        $value = strtoupper(trim((string) ($this->settings->get('paypal_currency') ?? 'USD')));

        return strlen($value) === 3 ? $value : 'USD';
    }

    public function setPaypalCurrency(string $currency): void
    {
        $currency = strtoupper(trim($currency));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('settings.invalid_currency');
        }

        $this->settings->set('paypal_currency', $currency);
    }
}
