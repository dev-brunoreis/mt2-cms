<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminGuildsController extends AdminController
{
    /** @var array<string, string> */
    private const TAB_TEMPLATES = [
        'membros' => 'components/guild-members.twig',
        'comentarios' => 'components/guild-comments.twig',
        'wars' => 'components/guild-wars.twig',
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
        private GuildRepository $guilds,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->guilds->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->guilds->countForGrid($q),
            fn ($q) => $this->guilds->listForGrid($q),
        );

        return $this->adminView('guilds', 'pages/guilds.twig', [
            'title' => $this->t('admin.guilds.title'),
            'pageLead' => $this->t('admin.guilds.lead'),
            'grid' => $grid,
        ]);
    }

    public function show(string $id): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $guildId = (int) $id;
        $guild = $this->guilds->findForAdmin($guildId);

        if ($guild === null) {
            $this->flash('error', $this->t('admin.guilds.not_found'));

            return $this->redirect('/admin/game/guilds');
        }

        $tab = $this->requestedTab(['dados', 'membros', 'comentarios', 'wars'], 'dados');
        $data = [
            'title' => $this->t('admin.guilds.view_title', ['name' => (string) $guild['name']]),
            'pageLead' => $this->t('admin.guilds.view_lead'),
            'formId' => 'admin-guild-form',
            'saveLabel' => $this->t('admin.save'),
            'guild' => $guild,
            'activeTab' => $tab,
            'error' => null,
        ];

        if ($this->wantsTabPartial()) {
            $template = self::TAB_TEMPLATES[$tab] ?? null;

            if ($template === null) {
                return Response::notFound();
            }

            return $this->adminFragment($template, $data);
        }

        return $this->adminView('guilds', 'pages/guild.twig', $data);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('guilds')) {
            return $redirect;
        }

        $guildId = (int) $id;
        $guild = $this->guilds->findForAdmin($guildId);

        if ($guild === null) {
            $this->flash('error', $this->t('admin.guilds.not_found'));

            return $this->redirect('/admin/game/guilds');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . $guildId);
        }

        $input = $this->formInput();

        try {
            $this->guilds->updateAdmin($guildId, $input);
            $this->audit('guild.update', 'guild', $guildId);
            $this->flash('success', $this->t('admin.guilds.updated'));

            return $this->redirect('/admin/game/guilds/' . $guildId);
        } catch (\InvalidArgumentException $e) {
            return $this->formView(
                array_merge($guild, $input),
                $this->t($e->getMessage()),
                422,
            );
        }
    }

    public function kick(string $id): Response
    {
        return $this->memberAction((int) $id, 'kick', 'admin.guilds.kicked');
    }

    public function deleteComment(string $id, string $commentId): Response
    {
        if ($redirect = $this->requireAdminSection('guilds')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . (int) $id . '?tab=comentarios');
        }

        if (!$this->guilds->deleteComment((int) $id, (int) $commentId)) {
            $this->flash('error', $this->t('admin.guilds.comment_not_found'));
        } else {
            $this->audit('guild.comment_delete', 'guild_comment', (int) $commentId, ['guild_id' => (int) $id]);
            $this->flash('success', $this->t('admin.guilds.comment_deleted'));
        }

        return $this->redirect('/admin/game/guilds/' . (int) $id . '?tab=comentarios');
    }

    public function dissolve(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('guilds')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . (int) $id);
        }

        if (!$this->guilds->dissolve((int) $id)) {
            $this->flash('error', $this->t('admin.guilds.not_found'));
        } else {
            $this->audit('guild.dissolve', 'guild', (int) $id);
            $this->flash('success', $this->t('admin.guilds.dissolved'));
        }

        return $this->redirect('/admin/game/guilds');
    }

    /**
     * @param array<string, mixed> $guild
     */
    private function formView(array $guild, ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $tab = $this->requestedTab(['dados', 'membros', 'comentarios', 'wars'], 'dados');

        return $this->adminView('guilds', 'pages/guild.twig', [
            'title' => $this->t('admin.guilds.view_title', ['name' => (string) ($guild['name'] ?? '')]),
            'pageLead' => $this->t('admin.guilds.view_lead'),
            'formId' => 'admin-guild-form',
            'saveLabel' => $this->t('admin.save'),
            'guild' => $guild,
            'activeTab' => $tab,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'level' => (int) ($_POST['level'] ?? 0),
            'exp' => (int) ($_POST['exp'] ?? 0),
            'gold' => (int) ($_POST['gold'] ?? 0),
            'ladder_point' => (int) ($_POST['ladder_point'] ?? 0),
            'win' => (int) ($_POST['win'] ?? 0),
            'draw' => (int) ($_POST['draw'] ?? 0),
            'loss' => (int) ($_POST['loss'] ?? 0),
        ];
    }

    private function memberAction(int $guildId, string $action, string $successKey): Response
    {
        if ($redirect = $this->requireAdminSection('guilds')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . $guildId . '?tab=membros');
        }

        $playerId = (int) ($_POST['player_id'] ?? 0);

        try {
            $ok = $action === 'kick' && $this->guilds->kickMember($guildId, $playerId);

            if (!$ok) {
                $this->flash('error', $this->t('admin.guilds.member_not_found'));
            } else {
                $this->audit('guild.kick', 'guild', $guildId, ['player_id' => $playerId]);
                $this->flash('success', $this->t($successKey));
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/game/guilds/' . $guildId . '?tab=membros');
    }
}
