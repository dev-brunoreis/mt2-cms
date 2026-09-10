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
        if ($redirect = $this->requireAdminSection('registration')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/registration');
        }

        $enabled = isset($_POST['registration_enabled']);
        $this->settings->setRegistrationEnabled($enabled);
        $this->audit('settings.registration_save', 'settings', null);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/registration');
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
        if ($redirect = $this->requireAdminSection('themes')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/themes');
        }

        $selected = $_POST['themes'] ?? [];
        $active = trim((string) ($_POST['active_theme'] ?? ''));

        if (!is_array($selected)) {
            $selected = [];
        }

        $themes = array_values(array_filter($selected, static fn ($value): bool => is_string($value)));

        try {
            $this->settings->setAvailableThemes($themes, $active);
            $this->audit('settings.themes_save', 'settings', null);
            $this->flash('success', $this->t('admin.saved'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/themes');
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
        if ($redirect = $this->requireAdminSection('locale')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/locale');
        }

        $locale = trim((string) ($_POST['default_locale'] ?? ''));

        if (!$this->locales->isSupported($locale)) {
            $this->flash('error', $this->t('admin.invalid_locale'));

            return $this->redirect('/admin/locale');
        }

        $this->settings->setDefaultLocale($locale);
        $this->audit('settings.locale_save', 'settings', null);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/locale');
    }
}
