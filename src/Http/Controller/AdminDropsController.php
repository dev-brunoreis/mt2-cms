<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\DropFileService;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\MobDropService;
use Mt2Cms\Theme\ThemeEngine;

class AdminDropsController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private DropFileService $drops,
        private MobDropService $mobDrops,
        private GameProtoService $protos,
        private GameProfile $profile,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        return $this->adminView('drops', 'pages/drops-index.twig', [
            'title' => $this->t('admin.drops.title'),
            'pageLead' => $this->t('admin.drops.lead'),
        ]);
    }

    public function etc(): Response
    {
        $tab = $this->requestedTab(['list'], 'list');

        return $this->adminView('drops', 'pages/drops-etc.twig', [
            'title' => $this->t('admin.drops.etc_title'),
            'pageLead' => $this->t('admin.drops.etc_lead'),
            'formId' => 'admin-drops-etc-form',
            'saveLabel' => $this->t('admin.save'),
            'drops' => $this->drops->etcDrops(),
            'activeTab' => $tab,
        ]);
    }

    public function saveEtc(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/drops/etc');
        }

        try {
            $rows = $this->parseEtcInput();
            $this->drops->saveEtcDrops($rows);
            $this->mobDrops->clearCatalog();
            $this->flash('success', $this->t('admin.drops.saved'));
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/drops/etc');
    }

    public function common(): Response
    {
        $data = $this->drops->commonDrops();

        return $this->adminView('drops', 'pages/drops-common.twig', [
            'title' => $this->t('admin.drops.common_title'),
            'pageLead' => $this->t('admin.drops.common_lead'),
            'formId' => 'admin-drops-common-form',
            'saveLabel' => $this->t('admin.save'),
            'header' => $data['header'],
            'ranks' => $data['ranks'],
            'rankNames' => $this->commonRankNames(),
        ]);
    }

    public function saveCommon(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/drops/common');
        }

        try {
            $this->drops->saveCommonDrops($this->parseCommonInput());
            $this->mobDrops->clearCatalog();
            $this->flash('success', $this->t('admin.drops.saved'));
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/drops/common');
    }

    public function mob(string $id): Response
    {
        $mobVnum = (int) $id;
        $mob = $this->protos->find(ProtoSchemas::KIND_MOB, $mobVnum);

        if ($mob === null) {
            $this->flash('error', $this->t('admin.drops.mob_not_found'));

            return $this->redirect('/admin/drops');
        }

        return $this->adminView('drops', 'pages/drops-mob.twig', [
            'title' => $this->t('admin.drops.mob_title', ['vnum' => (string) $mobVnum]),
            'pageLead' => $this->t('admin.drops.mob_lead'),
            'formId' => 'admin-drops-mob-form',
            'saveLabel' => $this->t('admin.save'),
            'mob' => $mob,
            'groups' => $this->drops->mobDropGroupsFor($mobVnum),
        ]);
    }

    public function saveMob(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $mobVnum = (int) $id;

        if ($this->protos->find(ProtoSchemas::KIND_MOB, $mobVnum) === null) {
            $this->flash('error', $this->t('admin.drops.mob_not_found'));

            return $this->redirect('/admin/drops');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/drops/mob/' . $mobVnum);
        }

        try {
            $this->drops->saveMobDropGroupsFor($mobVnum, $this->parseMobGroupsInput());
            $this->mobDrops->clearCatalog();
            $this->flash('success', $this->t('admin.drops.saved'));
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/drops/mob/' . $mobVnum);
    }

    /**
     * @return list<array{name: string, chance: string}>
     */
    private function parseEtcInput(): array
    {
        $names = $_POST['name'] ?? [];
        $chances = $_POST['chance'] ?? [];
        $rows = [];

        if (!is_array($names) || !is_array($chances)) {
            return [];
        }

        foreach ($names as $index => $name) {
            $rows[] = [
                'name' => trim((string) $name),
                'chance' => trim((string) ($chances[$index] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function parseCommonInput(): array
    {
        $ranks = [];
        $rankNames = $this->commonRankNames();

        foreach ($rankNames as $rank) {
            $labels = $_POST['label_' . $rank] ?? [];
            $levelStarts = $_POST['level_start_' . $rank] ?? [];
            $levelEnds = $_POST['level_end_' . $rank] ?? [];
            $chances = $_POST['chance_' . $rank] ?? [];
            $itemRefs = $_POST['item_ref_' . $rank] ?? [];
            $oneIns = $_POST['one_in_' . $rank] ?? [];
            $rows = [];

            if (!is_array($labels)) {
                $ranks[$rank] = [];

                continue;
            }

            foreach ($labels as $index => $label) {
                $itemRef = trim((string) ($itemRefs[$index] ?? ''));

                if ($itemRef === '') {
                    continue;
                }

                $rows[] = [
                    'label' => trim((string) $label),
                    'level_start' => (int) ($levelStarts[$index] ?? 1),
                    'level_end' => (int) ($levelEnds[$index] ?? 15),
                    'chance' => trim((string) ($chances[$index] ?? '0')),
                    'item_ref' => $itemRef,
                    'one_in' => (int) ($oneIns[$index] ?? 0),
                ];
            }

            $ranks[$rank] = $rows;
        }

        return $ranks;
    }

    /**
     * @return list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}>
     */
    private function parseMobGroupsInput(): array
    {
        $rawGroups = $_POST['groups'] ?? [];

        if (!is_array($rawGroups)) {
            return [];
        }

        $groups = [];

        foreach ($rawGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $name = trim((string) ($group['name'] ?? 'Group'));
            $type = trim((string) ($group['type'] ?? 'drop'));
            $killDrop = trim((string) ($group['kill_drop'] ?? ''));
            $levelLimit = trim((string) ($group['level_limit'] ?? ''));
            $attrs = [
                'type' => [$type],
            ];

            if ($killDrop !== '') {
                $attrs['kill_drop'] = [$killDrop];
            }

            if ($levelLimit !== '') {
                $attrs['level_limit'] = [$levelLimit];
            }

            $items = [];
            $itemRows = $group['items'] ?? [];

            if (is_array($itemRows)) {
                foreach ($itemRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $vnum = trim((string) ($row['vnum'] ?? ''));

                    if ($vnum === '') {
                        continue;
                    }

                    $line = [
                        $vnum,
                        trim((string) ($row['count'] ?? '1')),
                        trim((string) ($row['chance'] ?? '0')),
                    ];

                    $rare = trim((string) ($row['rare'] ?? ''));

                    if ($rare !== '') {
                        $line[] = $rare;
                    }

                    $items[] = $line;
                }
            }

            $groups[] = [
                'name' => $name !== '' ? $name : 'Group',
                'attrs' => $attrs,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function commonRankNames(): array
    {
        return $this->profile->commonRanks();
    }
}
