<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Support\Env;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Support\AppCrypto;
use Mt2Cms\Support\Money;

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

    public function moneyFormat(): string
    {
        return Money::normalizeFormat((string) ($this->settings->get('money_format') ?? Money::FORMAT_DOT));
    }

    public function setMoneyFormat(string $format): void
    {
        if (!in_array($format, Money::FORMATS, true)) {
            throw new \InvalidArgumentException('admin.locale.money_format_invalid');
        }

        $this->settings->set('money_format', $format);
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

    public function newsShowViews(): bool
    {
        $value = $this->settings->get('news_show_views');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setNewsShowViews(bool $enabled): void
    {
        $this->settings->set('news_show_views', $enabled ? '1' : '0');
    }

    public function siteTitle(): string
    {
        return trim((string) ($this->settings->get('site_title') ?? ''));
    }

    public function setSiteTitle(string $title): void
    {
        $title = trim($title);

        if (mb_strlen($title) > 100) {
            throw new \InvalidArgumentException('admin.community.site_title_invalid');
        }

        $this->settings->set('site_title', $title);
    }

    public function footerText(): string
    {
        return trim((string) ($this->settings->get('footer_text') ?? ''));
    }

    public function setFooterText(string $text): void
    {
        $text = trim($text);

        if (mb_strlen($text) > 500) {
            throw new \InvalidArgumentException('admin.community.footer_text_invalid');
        }

        $this->settings->set('footer_text', $text);
    }

    /**
     * @return list<string>
     */
    public static function socialNetworks(): array
    {
        return ['facebook', 'instagram', 'youtube', 'twitter', 'tiktok', 'twitch'];
    }

    /**
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $stored = $this->settings->getJson('social_links', []);
        $links = [];

        foreach (self::socialNetworks() as $network) {
            $url = trim((string) ($stored[$network] ?? ''));
            $links[$network] = $url;
        }

        return $links;
    }

    /**
     * Enabled social links for the public footer (network => url).
     *
     * @return array<string, string>
     */
    public function socialLinksEnabled(): array
    {
        $enabled = [];

        foreach ($this->socialLinks() as $network => $url) {
            if ($url !== '') {
                $enabled[$network] = $url;
            }
        }

        $discord = $this->discordInviteUrl();

        if ($discord !== '') {
            $enabled = ['discord' => $discord] + $enabled;
        }

        return $enabled;
    }

    /**
     * @param array<string, mixed> $links
     */
    public function setSocialLinks(array $links): void
    {
        $normalized = [];

        foreach (self::socialNetworks() as $network) {
            $url = trim((string) ($links[$network] ?? ''));

            if ($url === '') {
                $normalized[$network] = '';
                continue;
            }

            if (!preg_match('#^https://#i', $url)) {
                throw new \InvalidArgumentException('admin.community.social_url_invalid');
            }

            $normalized[$network] = rtrim($url, '/');
        }

        $this->settings->setJson('social_links', $normalized);
    }

    public function bannerIntervalMs(): int
    {
        $value = $this->settings->get('banner_interval_ms');

        if ($value === null || $value === '') {
            return 5500;
        }

        return max(2000, min(60000, (int) $value));
    }

    public function setBannerIntervalMs(int $ms): void
    {
        $this->settings->set('banner_interval_ms', (string) max(2000, min(60000, $ms)));
    }

    public function bannerAutoplay(): bool
    {
        $value = $this->settings->get('banner_autoplay');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setBannerAutoplay(bool $enabled): void
    {
        $this->settings->set('banner_autoplay', $enabled ? '1' : '0');
    }

    public function bannerShowDots(): bool
    {
        $value = $this->settings->get('banner_show_dots');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setBannerShowDots(bool $enabled): void
    {
        $this->settings->set('banner_show_dots', $enabled ? '1' : '0');
    }

    public function bannerShowArrows(): bool
    {
        $value = $this->settings->get('banner_show_arrows');

        if ($value === null) {
            return true;
        }

        return $value === '1';
    }

    public function setBannerShowArrows(bool $enabled): void
    {
        $this->settings->set('banner_show_arrows', $enabled ? '1' : '0');
    }

    /**
     * @return array{interval_ms: int, autoplay: bool, show_dots: bool, show_arrows: bool}
     */
    public function bannerSettings(): array
    {
        return [
            'interval_ms' => $this->bannerIntervalMs(),
            'autoplay' => $this->bannerAutoplay(),
            'show_dots' => $this->bannerShowDots(),
            'show_arrows' => $this->bannerShowArrows(),
        ];
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
            return false;
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
            throw new \InvalidArgumentException('admin.invalid_mail_from');
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
            throw new \InvalidArgumentException('admin.invalid_site_url');
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
            throw new \InvalidArgumentException('admin.invalid_currency');
        }

        $this->settings->set('paypal_currency', $currency);
    }

    public function paypalClientId(): string
    {
        $fromSettings = trim((string) ($this->settings->get('paypal_client_id') ?? ''));

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim((string) (Env::getInstance()->get('PAYPAL_CLIENT_ID') ?? ''));
    }

    public function paypalClientSecret(): string
    {
        $stored = (string) ($this->settings->get('paypal_client_secret') ?? '');

        if ($stored !== '') {
            try {
                return AppCrypto::isEncrypted($stored) ? AppCrypto::decrypt($stored) : $stored;
            } catch (\Throwable) {
                return '';
            }
        }

        return trim((string) (Env::getInstance()->get('PAYPAL_CLIENT_SECRET') ?? ''));
    }

    public function paypalConfigured(): bool
    {
        return $this->paypalClientId() !== ''
            && $this->paypalClientSecret() !== ''
            && $this->paypalWebhookId() !== '';
    }

    public function setPaypalClientId(string $clientId): void
    {
        $this->settings->set('paypal_client_id', trim($clientId));
    }

    public function setPaypalClientSecret(string $secret): void
    {
        $secret = trim($secret);

        if ($secret === '') {
            return;
        }

        $this->settings->set('paypal_client_secret', AppCrypto::encrypt($secret));
    }

    public function paypalWebhookId(): string
    {
        return trim((string) ($this->settings->get('paypal_webhook_id') ?? ''));
    }

    public function setPaypalWebhookId(string $webhookId): void
    {
        $this->settings->set('paypal_webhook_id', trim($webhookId));
    }

    public function paypalPendingMinutes(): int
    {
        $value = (int) ($this->settings->get('paypal_pending_minutes') ?? 30);

        return max(5, min(180, $value > 0 ? $value : 30));
    }

    public function setPaypalPendingMinutes(int $minutes): void
    {
        $this->settings->set('paypal_pending_minutes', (string) max(5, min(180, $minutes)));
    }

    public function discordInviteUrl(): string
    {
        return trim((string) ($this->settings->get('discord_invite_url') ?? ''));
    }

    public function setDiscordInviteUrl(string $url): void
    {
        $url = trim($url);

        if ($url === '') {
            $this->settings->set('discord_invite_url', '');

            return;
        }

        if (!preg_match('#^https://#i', $url)) {
            throw new \InvalidArgumentException('admin.community.discord_invite_invalid');
        }

        $this->settings->set('discord_invite_url', rtrim($url, '/'));
    }

    public function discordWebhookUrl(): string
    {
        $stored = trim((string) ($this->settings->get('discord_webhook_url') ?? ''));

        if ($stored === '') {
            return '';
        }

        try {
            return AppCrypto::isEncrypted($stored) ? AppCrypto::decrypt($stored) : $stored;
        } catch (\Throwable) {
            return '';
        }
    }

    public function discordWebhookConfigured(): bool
    {
        return $this->discordWebhookUrl() !== '';
    }

    public function setDiscordWebhookUrl(string $url): void
    {
        $url = trim($url);

        if ($url === '') {
            return;
        }

        if (!preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/#i', $url)) {
            throw new \InvalidArgumentException('admin.community.discord_webhook_invalid');
        }

        $this->settings->set('discord_webhook_url', AppCrypto::encrypt($url));
    }

    public function unstuckEnabled(): bool
    {
        $value = $this->settings->get('unstuck_enabled');

        if ($value === null) {
            return false;
        }

        return $value === '1';
    }

    public function setUnstuckEnabled(bool $enabled): void
    {
        $this->settings->set('unstuck_enabled', $enabled ? '1' : '0');
    }

    public function unstuckCooldownMinutes(): int
    {
        $value = (int) ($this->settings->get('unstuck_cooldown_minutes') ?? 60);

        return max(1, min(1440, $value));
    }

    public function setUnstuckCooldownMinutes(int $minutes): void
    {
        $this->settings->set('unstuck_cooldown_minutes', (string) max(1, min(1440, $minutes)));
    }

    /**
     * @return array<int, array{map_index: int, x: int, y: int}>
     */
    public function unstuckSpawns(): array
    {
        $stored = $this->settings->getJson('unstuck_spawns', []);
        $defaults = self::defaultUnstuckSpawns();
        $spawns = [];

        foreach ([1, 2, 3] as $empire) {
            $raw = $stored[(string) $empire] ?? $stored[$empire] ?? null;

            if (is_array($raw)) {
                $spawns[$empire] = [
                    'map_index' => max(0, (int) ($raw['map_index'] ?? 0)),
                    'x' => (int) ($raw['x'] ?? 0),
                    'y' => (int) ($raw['y'] ?? 0),
                ];
            } else {
                $spawns[$empire] = $defaults[$empire];
            }
        }

        return $spawns;
    }

    /**
     * @param array<int, array{map_index: int, x: int, y: int}> $spawns
     */
    public function setUnstuckSpawns(array $spawns): void
    {
        $normalized = [];

        foreach ([1, 2, 3] as $empire) {
            $raw = $spawns[$empire] ?? [];
            $normalized[(string) $empire] = [
                'map_index' => max(0, (int) ($raw['map_index'] ?? 0)),
                'x' => (int) ($raw['x'] ?? 0),
                'y' => (int) ($raw['y'] ?? 0),
            ];
        }

        $this->settings->setJson('unstuck_spawns', $normalized);
    }

    /**
     * @return array{map_index: int, x: int, y: int}|null
     */
    public function unstuckSpawnForEmpire(int $empire): ?array
    {
        $spawns = $this->unstuckSpawns();
        $spawn = $spawns[$empire] ?? null;

        if ($spawn === null || ($spawn['map_index'] === 0 && $spawn['x'] === 0 && $spawn['y'] === 0)) {
            return null;
        }

        return $spawn;
    }

    public function referralEnabled(): bool
    {
        $value = $this->settings->get('referral_enabled');

        if ($value === null) {
            return false;
        }

        return $value === '1';
    }

    public function setReferralEnabled(bool $enabled): void
    {
        $this->settings->set('referral_enabled', $enabled ? '1' : '0');
    }

    public function referralRewardCash(): int
    {
        return max(0, (int) ($this->settings->get('referral_reward_cash') ?? 0));
    }

    public function setReferralRewardCash(int $amount): void
    {
        $this->settings->set('referral_reward_cash', (string) max(0, $amount));
    }

    public function referralMinLevel(): int
    {
        return max(1, (int) ($this->settings->get('referral_min_level') ?? 10));
    }

    public function setReferralMinLevel(int $level): void
    {
        $this->settings->set('referral_min_level', (string) max(1, $level));
    }

    public function referralCap(): int
    {
        return max(0, (int) ($this->settings->get('referral_cap') ?? 0));
    }

    public function setReferralCap(int $cap): void
    {
        $this->settings->set('referral_cap', (string) max(0, $cap));
    }

    /**
     * @return array<int, array{map_index: int, x: int, y: int}>
     */
    private static function defaultUnstuckSpawns(): array
    {
        return [
            1 => ['map_index' => 1, 'x' => 469300, 'y' => 964200],
            2 => ['map_index' => 21, 'x' => 55700, 'y' => 157900],
            3 => ['map_index' => 41, 'x' => 969600, 'y' => 278400],
        ];
    }
}
