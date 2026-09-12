<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Service\UnstuckService;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Theme\ThemeEngine;

class AdminSettingsController extends AdminController
{
    private const TABS = ['registration', 'themes', 'locale', 'security', 'community', 'unstuck', 'banners'];

    /** @var array<string, string> */
    private const TAB_VIEW_RESOURCES = [
        'registration' => 'settings/registration/view',
        'themes' => 'settings/themes/view',
        'locale' => 'settings/locale/view',
        'security' => 'settings/security/view',
        'community' => 'settings/community/view',
        'unstuck' => 'settings/unstuck/view',
        'banners' => 'content/banners/settings/view',
    ];

    /** @var array<string, string> */
    private const TAB_FORMS = [
        'registration' => 'admin-registration-form',
        'themes' => 'admin-themes-form',
        'locale' => 'admin-locale-form',
        'security' => 'admin-security-form',
        'community' => 'admin-community-form',
        'unstuck' => 'admin-unstuck-form',
        'banners' => 'admin-banner-settings-form',
    ];

    /** @var array<string, string> */
    private const TAB_LABELS = [
        'registration' => 'admin.nav.registration',
        'themes' => 'admin.nav.themes',
        'locale' => 'admin.nav.locale',
        'security' => 'admin.nav.security',
        'community' => 'admin.nav.community',
        'unstuck' => 'admin.nav.unstuck',
        'banners' => 'admin.nav.banners',
    ];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private SettingsService $settings,
        private ThemeCatalog $themes,
        private Locales $locales,
        private ServerChannelRepository $channels,
        private UnstuckService $unstuck,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $tab = $this->resolveResourceTab(self::TABS, self::TAB_VIEW_RESOURCES, 'registration');

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        return $this->adminView('settings', 'pages/settings-hub.twig', [
            'title' => $this->t('admin.settings.hub_title'),
            'pageLead' => $this->t('admin.settings.hub_lead'),
            'formId' => self::TAB_FORMS[$tab],
            'activeTab' => $tab,
            'defaultTab' => 'registration',
            'settingsBaseUrl' => AdminPaths::settings(),
            'tabs' => $this->hubTabs(),
            'initialPartial' => $this->partialPayload($tab),
        ]);
    }

    public function registration(): Response
    {
        return $this->redirect(AdminPaths::settingsRegistration());
    }

    public function themes(): Response
    {
        return $this->redirect(AdminPaths::settingsThemes());
    }

    public function locale(): Response
    {
        return $this->redirect(AdminPaths::settingsLocale());
    }

    public function security(): Response
    {
        return $this->redirect(AdminPaths::settingsSecurity());
    }

    public function saveRegistration(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/registration/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsRegistration());
        }

        $enabled = isset($_POST['registration_enabled']);
        $before = ['registration_enabled' => $this->settings->registrationEnabled()];
        $this->settings->setRegistrationEnabled($enabled);
        $this->auditChange(
            'settings.registration_save',
            'settings',
            null,
            $before,
            ['registration_enabled' => $enabled],
        );
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::settingsRegistration());
    }

    public function saveThemes(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/themes/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsThemes());
        }

        $selected = $_POST['themes'] ?? [];
        $active = trim((string) ($_POST['active_theme'] ?? ''));

        if (!is_array($selected)) {
            $selected = [];
        }

        $themes = array_values(array_filter($selected, static fn ($value): bool => is_string($value)));

        try {
            $before = [
                'themes' => $this->settings->availableThemes(),
                'active_theme' => $this->settings->activeTheme(),
            ];
            $this->settings->setAvailableThemes($themes, $active);
            $this->auditChange('settings.themes_save', 'settings', null, $before, [
                'themes' => $themes,
                'active_theme' => $active,
            ]);
            $this->flash('success', $this->t('admin.saved'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect(AdminPaths::settingsThemes());
    }

    public function saveLocale(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/locale/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsLocale());
        }

        $locale = trim((string) ($_POST['default_locale'] ?? ''));

        if (!$this->locales->isSupported($locale)) {
            $this->flash('error', $this->t('admin.invalid_locale'));

            return $this->redirect(AdminPaths::settingsLocale());
        }

        $before = ['default_locale' => $this->settings->defaultLocale()];
        $this->settings->setDefaultLocale($locale);
        $this->auditChange('settings.locale_save', 'settings', null, $before, [
            'default_locale' => $locale,
        ]);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::settingsLocale());
    }

    public function saveSecurity(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/security/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsSecurity());
        }

        $before = [
            'captcha_public' => $this->settings->captchaPublicEnabled(),
            'captcha_admin' => $this->settings->captchaAdminEnabled(),
            'admin_2fa_required' => $this->settings->adminTwoFactorRequired(),
        ];
        $captchaPublic = isset($_POST['captcha_public']);
        $captchaAdmin = isset($_POST['captcha_admin']);
        $twoFactorRequired = isset($_POST['admin_2fa_required']);

        $this->settings->setCaptchaPublicEnabled($captchaPublic);
        $this->settings->setCaptchaAdminEnabled($captchaAdmin);
        $this->settings->setAdminTwoFactorRequired($twoFactorRequired);
        $this->auditChange('settings.security_save', 'settings', null, $before, [
            'captcha_public' => $captchaPublic,
            'captcha_admin' => $captchaAdmin,
            'admin_2fa_required' => $twoFactorRequired,
        ]);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::settingsSecurity());
    }

    public function saveBanners(): Response
    {
        if ($redirect = $this->requireAdminResource('content/banners/settings/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsBanners());
        }

        $before = $this->settings->bannerSettings();
        $after = [
            'interval_ms' => max(2000, min(60000, (int) ($_POST['banner_interval_ms'] ?? 5500))),
            'autoplay' => isset($_POST['banner_autoplay']),
            'show_dots' => isset($_POST['banner_show_dots']),
            'show_arrows' => isset($_POST['banner_show_arrows']),
        ];

        $this->settings->setBannerIntervalMs($after['interval_ms']);
        $this->settings->setBannerAutoplay($after['autoplay']);
        $this->settings->setBannerShowDots($after['show_dots']);
        $this->settings->setBannerShowArrows($after['show_arrows']);
        $this->auditChange('banner.settings_save', 'banner_settings', null, $before, $after);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect(AdminPaths::settingsBanners());
    }

    private function renderTabPartial(string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            return new Response('', 404);
        }

        if ($deny = $this->requireAdminResourceView(self::TAB_VIEW_RESOURCES[$tab])) {
            return $deny;
        }

        $payload = $this->partialPayload($tab);

        return $this->adminFragment($payload['template'], $payload['data']);
    }

    /**
     * @return list<array{id: string, label: string, resource: string, formId: string}>
     */
    private function hubTabs(): array
    {
        $tabs = [];

        foreach (self::TABS as $id) {
            $tabs[] = [
                'id' => $id,
                'label' => $this->t(self::TAB_LABELS[$id]),
                'resource' => self::TAB_VIEW_RESOURCES[$id],
                'formId' => self::TAB_FORMS[$id],
            ];
        }

        return $tabs;
    }

    /**
     * @return array{template: string, data: array<string, mixed>}
     */
    private function partialPayload(string $tab): array
    {
        $formId = self::TAB_FORMS[$tab];

        return match ($tab) {
            'themes' => [
                'template' => 'pages/themes.twig',
                'data' => [
                    'formId' => $formId,
                    'diskThemes' => $this->themes->available(),
                    'enabledThemes' => $this->settings->availableThemes(),
                    'activeTheme' => $this->settings->activeTheme(),
                ],
            ],
            'locale' => [
                'template' => 'pages/locale.twig',
                'data' => [
                    'formId' => $formId,
                    'availableLocales' => $this->locales->available(),
                    'defaultLocale' => $this->settings->defaultLocale(),
                ],
            ],
            'security' => [
                'template' => 'pages/security.twig',
                'data' => [
                    'formId' => $formId,
                    'captchaPublicEnabled' => $this->settings->captchaPublicEnabled(),
                    'captchaAdminEnabled' => $this->settings->captchaAdminEnabled(),
                    'adminTwoFactorRequired' => $this->settings->adminTwoFactorRequired(),
                ],
            ],
            'community' => [
                'template' => 'pages/community-channels.twig',
                'data' => [
                    'formId' => $formId,
                    'channels' => $this->channels->allForAdmin(),
                    'onlineWindowMinutes' => $this->settings->onlineWindowMinutes(),
                    'siteUrl' => $this->settings->siteUrl(),
                    'mailFromAddress' => $this->settings->mailFromAddress(),
                    'mailFromName' => $this->settings->mailFromName(),
                    'requireVerifiedEmail' => $this->settings->requireVerifiedEmail(),
                    'paypalMode' => $this->settings->paypalMode(),
                    'paypalCurrency' => $this->settings->paypalCurrency(),
                    'paypalClientId' => $this->settings->paypalClientId(),
                    'paypalConfigured' => $this->settings->paypalConfigured(),
                    'paypalWebhookId' => $this->settings->paypalWebhookId(),
                    'discordInviteUrl' => $this->settings->discordInviteUrl(),
                    'discordWebhookConfigured' => $this->settings->discordWebhookConfigured(),
                ],
            ],
            'unstuck' => [
                'template' => 'pages/unstuck-settings.twig',
                'data' => [
                    'formId' => $formId,
                    'unstuckEnabled' => $this->settings->unstuckEnabled(),
                    'cooldownMinutes' => $this->settings->unstuckCooldownMinutes(),
                    'spawns' => $this->settings->unstuckSpawns(),
                    'positionColumnsAvailable' => $this->unstuck->hasPositionColumns(),
                ],
            ],
            'banners' => [
                'template' => 'pages/banner-settings-partial.twig',
                'data' => [
                    'formId' => $formId,
                    'bannerSettings' => $this->settings->bannerSettings(),
                ],
            ],
            default => [
                'template' => 'pages/registration.twig',
                'data' => [
                    'formId' => $formId,
                    'registrationEnabled' => $this->settings->registrationEnabled(),
                ],
            ],
        };
    }
}
