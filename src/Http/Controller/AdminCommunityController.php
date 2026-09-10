<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Theme\ThemeEngine;

class AdminCommunityController extends AdminController
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
        private ServerChannelRepository $channels,
        private SettingsService $settings,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function channels(): Response
    {
        return $this->adminView('community', 'pages/community-channels.twig', [
            'title' => $this->t('admin.community.channels_title'),
            'pageLead' => $this->t('admin.community.channels_lead'),
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
            'formId' => 'admin-community-form',
            'saveLabel' => $this->t('admin.save'),
        ]);
    }

    public function saveChannels(): Response
    {
        if ($redirect = $this->requireAdminResource('settings/community/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/community');
        }

        $this->settings->setOnlineWindowMinutes((int) ($_POST['online_window_minutes'] ?? 15));

        try {
            $this->settings->setSiteUrl(trim((string) ($_POST['site_url'] ?? '')));
            $this->settings->setMailFrom(
                trim((string) ($_POST['mail_from_address'] ?? '')),
                trim((string) ($_POST['mail_from_name'] ?? '')),
            );
            $this->settings->setRequireVerifiedEmail(isset($_POST['require_verified_email']));
            $this->settings->setPaypalMode(trim((string) ($_POST['paypal_mode'] ?? 'sandbox')));
            $this->settings->setPaypalCurrency(trim((string) ($_POST['paypal_currency'] ?? 'USD')));
            $this->settings->setPaypalClientId(trim((string) ($_POST['paypal_client_id'] ?? '')));
            $this->settings->setPaypalClientSecret(trim((string) ($_POST['paypal_client_secret'] ?? '')));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));

            return $this->redirect('/admin/settings/community');
        }

        $names = $_POST['channel_name'] ?? [];
        $labels = $_POST['channel_label'] ?? [];
        $hosts = $_POST['channel_host'] ?? [];
        $ports = $_POST['channel_port'] ?? [];
        $sorts = $_POST['channel_sort'] ?? [];
        $enabled = $_POST['channel_enabled'] ?? [];
        $ids = $_POST['channel_id'] ?? [];

        if (!is_array($names)) {
            $names = [];
        }

        $seen = [];

        foreach ($names as $index => $name) {
            if (!is_string($name)) {
                continue;
            }

            $name = trim($name);
            $label = trim((string) ($labels[$index] ?? ''));
            $host = trim((string) ($hosts[$index] ?? ''));
            $portRaw = trim((string) ($ports[$index] ?? ''));
            $port = $portRaw !== '' ? max(1, (int) $portRaw) : null;
            $sort = max(0, (int) ($sorts[$index] ?? 0));
            $isEnabled = isset($enabled[$index]);
            $id = (int) ($ids[$index] ?? 0);

            if ($name === '' && $label === '') {
                continue;
            }

            if ($name === '' || $label === '') {
                continue;
            }

            $hostValue = $host !== '' ? $host : null;

            if ($id > 0) {
                $this->channels->update($id, $name, $label, $hostValue, $port, $sort, $isEnabled);
                $seen[] = $id;
            } else {
                $seen[] = $this->channels->create($name, $label, $hostValue, $port, $sort, $isEnabled);
            }
        }

        foreach ($this->channels->allForAdmin() as $channel) {
            if (!in_array((int) $channel['id'], $seen, true)) {
                $this->channels->delete((int) $channel['id']);
            }
        }

        $this->audit('settings.community_save', 'settings', null);
        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect('/admin/settings/community');
    }

    public function deleteChannel(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('settings/community/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/settings/community');
        }

        $channelId = (int) $id;
        $channel = $this->channels->findById($channelId);

        if ($channel === null) {
            $this->flash('error', $this->t('admin.community.channel_not_found'));

            return $this->redirect('/admin/settings/community');
        }

        $this->channels->delete($channelId);
        $this->audit('settings.channel_delete', 'server_channel', $channelId, [
            'name' => (string) ($channel['name'] ?? ''),
        ]);
        $this->flash('success', $this->t('admin.community.channel_removed'));

        return $this->redirect('/admin/settings/community');
    }
}
