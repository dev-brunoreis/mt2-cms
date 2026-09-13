<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\LogoUploadService;
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
        private LogoUploadService $logoUploads,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function general(): Response
    {
        return $this->redirect(AdminPaths::settingsCommunity());
    }

    public function social(): Response
    {
        return $this->redirect(AdminPaths::settingsSocial());
    }

    public function discord(): Response
    {
        return $this->redirect(AdminPaths::settingsDiscord());
    }

    public function channels(): Response
    {
        return $this->redirect(AdminPaths::settingsChannels());
    }

    public function saveGeneral(): Response
    {
        return $this->saveSection(AdminPaths::settingsCommunity(), function (): void {
            $this->settings->setOnlineWindowMinutes((int) ($_POST['online_window_minutes'] ?? 15));
            $this->settings->setSiteTitle(trim((string) ($_POST['site_title'] ?? '')));
            $this->replaceSiteLogo();
            $this->settings->setFooterText(trim((string) ($_POST['footer_text'] ?? '')));
            $this->settings->setSiteUrl(trim((string) ($_POST['site_url'] ?? '')));
            $this->settings->setMailFrom(
                trim((string) ($_POST['mail_from_address'] ?? '')),
                trim((string) ($_POST['mail_from_name'] ?? '')),
            );
            $this->settings->setRequireVerifiedEmail(isset($_POST['require_verified_email']));
            $this->settings->setPublicPlayerEquipment(isset($_POST['public_player_equipment']));
            $this->audit('settings.community_save', 'settings', null);
        });
    }

    public function saveSocial(): Response
    {
        return $this->saveSection(AdminPaths::settingsSocial(), function (): void {
            $social = $_POST['social'] ?? [];
            $this->settings->setSocialLinks(is_array($social) ? $social : []);
            $this->audit('settings.social_save', 'settings', null);
        });
    }

    public function saveDiscord(): Response
    {
        return $this->saveSection(AdminPaths::settingsDiscord(), function (): void {
            $this->settings->setDiscordInviteUrl(trim((string) ($_POST['discord_invite_url'] ?? '')));
            $this->settings->setDiscordWebhookUrl(trim((string) ($_POST['discord_webhook_url'] ?? '')));
            $this->audit('settings.discord_save', 'settings', null);
        });
    }

    public function saveChannels(): Response
    {
        return $this->saveSection(AdminPaths::settingsChannels(), function (): void {
            $this->syncChannelsFromPost();
            $this->audit('settings.channels_save', 'settings', null);
        });
    }

    public function deleteChannel(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('settings/community/edit')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::settingsChannels());
        }

        $channelId = (int) $id;
        $channel = $this->channels->findById($channelId);

        if ($channel === null) {
            $this->flash('error', $this->t('admin.community.channel_not_found'));

            return $this->redirect(AdminPaths::settingsChannels());
        }

        $this->channels->delete($channelId);
        $this->audit('settings.channel_delete', 'server_channel', $channelId, [
            'name' => (string) ($channel['name'] ?? ''),
        ]);
        $this->flash('success', $this->t('admin.community.channel_removed'));

        return $this->redirect(AdminPaths::settingsChannels());
    }

    /**
     * @param callable(): void $save
     */
    private function saveSection(string $redirect, callable $save): Response
    {
        if ($deny = $this->requireAdminResource('settings/community/edit')) {
            return $deny;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($redirect);
        }

        try {
            $save();
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));

            return $this->redirect($redirect);
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t('admin.community.logo_upload_failed'));

            return $this->redirect($redirect);
        }

        $this->flash('success', $this->t('admin.saved'));

        return $this->redirect($redirect);
    }

    private function replaceSiteLogo(): void
    {
        $file = $_FILES['site_logo'] ?? null;
        $hasUpload = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $remove = isset($_POST['remove_site_logo']);

        if (!$hasUpload && !$remove) {
            return;
        }

        $previous = $this->settings->siteLogo();

        if ($hasUpload) {
            $path = $this->logoUploads->store($file);
            $this->settings->setSiteLogo($path);
        } else {
            $this->settings->setSiteLogo('');
        }

        if ($previous !== '') {
            $this->logoUploads->delete($previous);
        }
    }

    private function syncChannelsFromPost(): void
    {
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
    }
}
