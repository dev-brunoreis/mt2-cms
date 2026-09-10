<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminSettingsController extends AdminController
{
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
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function registration(): Response
    {
        return $this->adminView('registration', 'pages/registration.twig', [
            'title' => $this->t('admin.registration.title'),
            'pageLead' => $this->t('admin.registration.lead'),
            'formId' => 'admin-registration-form',
            'registrationEnabled' => $this->settings->registrationEnabled(),
        ]);
    }

    public function saveRegistration(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/registration/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/registration');
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

        return $this->redirect('/admin/settings/registration');
    }

    public function themes(): Response
    {
        $diskThemes = $this->themes->available();
        $enabledThemes = $this->settings->availableThemes();

        return $this->adminView('themes', 'pages/themes.twig', [
            'title' => $this->t('admin.themes.title'),
            'pageLead' => $this->t('admin.themes.lead'),
            'formId' => 'admin-themes-form',
            'diskThemes' => $diskThemes,
            'enabledThemes' => $enabledThemes,
            'activeTheme' => $this->settings->activeTheme(),
        ]);
    }

    public function saveThemes(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/themes/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/themes');
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

        return $this->redirect('/admin/settings/themes');
    }

    public function locale(): Response
    {
        return $this->adminView('locale', 'pages/locale.twig', [
            'title' => $this->t('admin.locale.title'),
            'pageLead' => $this->t('admin.locale.lead'),
            'formId' => 'admin-locale-form',
            'availableLocales' => $this->locales->available(),
            'defaultLocale' => $this->settings->defaultLocale(),
        ]);
    }

    public function saveLocale(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/locale/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/locale');
        }

        $locale = trim((string) ($_POST['default_locale'] ?? ''));

        if (!$this->locales->isSupported($locale)) {
            $this->flash('error', $this->t('admin.invalid_locale'));

            return $this->redirect('/admin/settings/locale');
        }

        $before = ['default_locale' => $this->settings->defaultLocale()];
        $this->settings->setDefaultLocale($locale);
        $this->auditChange('settings.locale_save', 'settings', null, $before, [
            'default_locale' => $locale,
        ]);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/settings/locale');
    }

    public function security(): Response
    {
        return $this->adminView('security', 'pages/security.twig', [
            'title' => $this->t('admin.security.title'),
            'pageLead' => $this->t('admin.security.lead'),
            'formId' => 'admin-security-form',
            'captchaPublicEnabled' => $this->settings->captchaPublicEnabled(),
            'captchaAdminEnabled' => $this->settings->captchaAdminEnabled(),
            'adminTwoFactorRequired' => $this->settings->adminTwoFactorRequired(),
        ]);
    }

    public function saveSecurity(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/security/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/security');
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

        return $this->redirect('/admin/settings/security');
    }
}
