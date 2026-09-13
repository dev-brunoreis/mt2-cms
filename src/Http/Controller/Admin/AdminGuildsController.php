<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\GuildCommentsGrid;
use Mt2Cms\Admin\Grid\Definitions\GuildMembersGrid;
use Mt2Cms\Admin\Grid\Definitions\GuildsGrid;
use Mt2Cms\Admin\Grid\Definitions\GuildWarsGrid;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Admin\Grid\InMemoryGrid;
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
        'members' => 'components/guild-members.twig',
        'comments' => 'components/guild-comments.twig',
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
        $spec = GuildsGrid::definition()->spec();
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

        $tab = $this->requestedTab(['data', 'members', 'comments', 'wars'], 'data');
        $data = [
            'title' => $this->t('admin.guilds.view_title', ['name' => (string) $guild['name']]),
            'pageLead' => $this->t('admin.guilds.view_lead'),
            'formId' => 'admin-guild-form',
            'saveLabel' => $this->t('admin.save'),
            'guild' => $guild,
            'activeTab' => $tab,
            'error' => null,
        ];

        if ($tab === 'members') {
            $data['membersGrid'] = $this->membersGrid($guildId, $guild);
        } elseif ($tab === 'comments') {
            $data['commentsGrid'] = $this->commentsGrid($guildId, $guild);
        } elseif ($tab === 'wars') {
            $data['warsGrid'] = $this->warsGrid($guildId, $guild);
        }

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
        if ($redirect = $this->requireAdminResource('game/guilds/edit')) {
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
            $this->auditChange('guild.update', 'guild', $guildId, [
                'name' => $guild['name'],
                'level' => $guild['level'],
                'exp' => $guild['exp'],
                'gold' => $guild['gold'],
                'ladder_point' => $guild['ladder_point'],
                'win' => $guild['win'],
                'draw' => $guild['draw'],
                'loss' => $guild['loss'],
            ], $input);
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

    public function kick(string $id, string $playerId): Response
    {
        return $this->memberAction((int) $id, (int) $playerId, 'kick', 'admin.guilds.kicked');
    }

    public function deleteComment(string $id, string $commentId): Response
    {
        if ($redirect = $this->requireAdminResource('game/guilds/delete_comment')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . (int) $id . '?tab=comments');
        }

        if (!$this->guilds->deleteComment((int) $id, (int) $commentId)) {
            $this->flash('error', $this->t('admin.guilds.comment_not_found'));
        } else {
            $this->audit('guild.comment_delete', 'guild_comment', (int) $commentId, ['guild_id' => (int) $id]);
            $this->flash('success', $this->t('admin.guilds.comment_deleted'));
        }

        return $this->redirect('/admin/game/guilds/' . (int) $id . '?tab=comments');
    }

    public function dissolve(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('game/guilds/dissolve')) {
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
     * @return array<string, mixed>
     */
    private function membersGrid(int $guildId, array $guild): array
    {
        $masterId = (int) ($guild['master_id'] ?? 0);
        $rows = [];

        foreach ($guild['members'] ?? [] as $member) {
            if (!is_array($member)) {
                continue;
            }

            $pid = (int) ($member['id'] ?? 0);
            $rows[] = array_merge($member, [
                'can_kick' => $pid > 0 && $pid !== $masterId ? '1' : '0',
                'grade_name' => $member['grade_name'] ?? $member['grade'] ?? '',
            ]);
        }

        $def = GuildMembersGrid::definition($guildId);
        $spec = $def->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            static fn () => count($rows),
            static fn ($q) => InMemoryGrid::apply($rows, $q, $def->sortMap()),
        );
    }

    /**
     * @param array<string, mixed> $guild
     * @return array<string, mixed>
     */
    private function commentsGrid(int $guildId, array $guild): array
    {
        $rows = is_array($guild['comments'] ?? null) ? $guild['comments'] : [];
        $def = GuildCommentsGrid::definition($guildId);
        $spec = $def->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            static fn () => count($rows),
            static fn ($q) => InMemoryGrid::apply($rows, $q, $def->sortMap()),
        );
    }

    /**
     * @param array<string, mixed> $guild
     * @return array<string, mixed>
     */
    private function warsGrid(int $guildId, array $guild): array
    {
        $rows = [];

        foreach ($guild['wars'] ?? [] as $war) {
            if (!is_array($war)) {
                continue;
            }

            $isGuild1 = (int) ($war['guild1'] ?? 0) === $guildId;
            $opponentId = $isGuild1 ? (int) ($war['guild2'] ?? 0) : (int) ($war['guild1'] ?? 0);
            $opponentName = $isGuild1
                ? (string) ($war['guild2_name'] ?? $opponentId)
                : (string) ($war['guild1_name'] ?? $opponentId);

            $rows[] = array_merge($war, [
                'opponent_id' => $opponentId,
                'opponent_name' => $opponentName !== '' ? $opponentName : (string) $opponentId,
                'started_label' => !empty($war['started']) ? $this->t('admin.guilds.yes') : $this->t('admin.guilds.no'),
                'result_label' => ((int) ($war['result1'] ?? 0)) . ' : ' . ((int) ($war['result2'] ?? 0)),
            ]);
        }

        $def = GuildWarsGrid::definition($guildId);
        $spec = $def->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            static fn () => count($rows),
            static fn ($q) => InMemoryGrid::apply($rows, $q, $def->sortMap()),
        );
    }

    /**
     * @param array<string, mixed> $guild
     */
    private function formView(array $guild, ?string $error = null, int $status = 200): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        return $this->adminView('guilds', 'pages/guild.twig', [
            'title' => $this->t('admin.guilds.view_title', ['name' => (string) ($guild['name'] ?? '')]),
            'pageLead' => $this->t('admin.guilds.view_lead'),
            'formId' => 'admin-guild-form',
            'saveLabel' => $this->t('admin.save'),
            'guild' => $guild,
            'activeTab' => 'data',
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

    private function memberAction(int $guildId, int $playerId, string $action, string $successKey): Response
    {
        if ($redirect = $this->requireAdminResource('game/guilds/kick')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game/guilds/' . $guildId . '?tab=members');
        }

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

        return $this->redirect('/admin/game/guilds/' . $guildId . '?tab=members');
    }
}
