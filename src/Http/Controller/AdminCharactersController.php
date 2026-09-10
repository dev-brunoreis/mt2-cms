<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\InventoryLayout;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\ItemRepository;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminCharactersController extends AdminController
{
    /** @var array<string, string> */
    private const CHARACTER_TAB_TEMPLATES = [
        'logs' => 'components/character-logs.twig',
        'items' => 'components/character-items.twig',
        'guild' => 'components/character-guild.twig',
        'marriage' => 'components/character-marriage.twig',
    ];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AdminAuditService $auditLog,
        private PlayerRepository $players,
        private ItemRepository $items,
        private GuildRepository $guilds,
        private LogRepository $logs,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog);
    }

    public function index(): Response
    {
        $spec = $this->players->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->players->countForGrid($q),
            fn ($q) => $this->players->listForGrid($q),
        );

        return $this->adminView('characters', 'pages/characters.twig', [
            'title' => $this->t('admin.characters.title'),
            'pageLead' => $this->t('admin.characters.lead'),
            'grid' => $grid,
        ]);
    }

    public function show(string $id): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $character = $this->players->findForAdmin((int) $id);

        if ($character === null) {
            $this->flash('error', $this->t('admin.characters.not_found'));

            return $this->redirect('/admin/characters');
        }

        $playerId = (int) $character['id'];
        $accountId = (int) ($character['account_id'] ?? 0);
        $name = (string) $character['name'];
        $tab = $this->requestedTab(['dados', 'logs', 'items', 'guild', 'marriage'], 'dados');
        $data = [
            'title' => $this->t('admin.characters.view_title', ['name' => $name]),
            'pageLead' => $this->t('admin.characters.view_lead'),
            'character' => $character,
            'activeTab' => $tab,
            'characterLogs' => [],
            'characterItems' => [],
            'characterItemLayout' => null,
            'safebox' => null,
            'safeboxLayout' => null,
            'guild' => null,
            'marriage' => null,
        ];

        if ($tab === 'logs') {
            $data['characterLogs'] = $this->decorateLogs($this->logs->listForCharacter($playerId, $name));
        }

        if ($tab === 'items') {
            $characterItems = $this->items->forCharacter($playerId);
            $safebox = $accountId > 0 ? $this->items->safeboxForAccount($accountId) : null;
            $data['characterItems'] = $characterItems;
            $data['characterItemLayout'] = InventoryLayout::forCharacter($characterItems);
            $data['safebox'] = $safebox;
            $data['safeboxLayout'] = $safebox !== null
                ? InventoryLayout::forAccount($safebox['items'], (int) $safebox['size'])
                : null;
        }

        if ($tab === 'guild') {
            $data['guild'] = $this->guilds->profileForPlayer($playerId);
        }

        if ($tab === 'marriage') {
            $data['marriage'] = $this->players->findMarriageForPlayer($playerId);
        }

        if ($this->wantsTabPartial()) {
            $template = self::CHARACTER_TAB_TEMPLATES[$tab] ?? null;

            if ($template === null) {
                return Response::notFound();
            }

            return $this->adminFragment($template, $data);
        }

        return $this->adminView('characters', 'pages/character.twig', $data);
    }

    public function showOwnedItem(string $id): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $itemId = (int) $id;

        if ($itemId < 1) {
            $this->flash('error', $this->t('admin.owned_items.not_found'));

            return $this->redirect('/admin/characters');
        }

        $tab = $this->requestedTab(['dados', 'logs'], 'dados');
        $item = $this->items->findById($itemId);
        $itemLogs = [];

        if ($item === null || $tab === 'logs') {
            $itemLogs = $this->decorateLogs($this->logs->listForItem($itemId));
        }

        if ($item === null && $itemLogs === []) {
            $this->flash('error', $this->t('admin.owned_items.not_found'));

            return $this->redirect('/admin/characters');
        }

        $owner = null;
        $ownerKind = null;

        if ($item !== null) {
            $ownerId = (int) $item['owner_id'];
            $ownerKind = ItemRepository::isAccountWindow((string) $item['window']) ? 'account' : 'character';
            $owner = $ownerKind === 'account'
                ? $this->accounts->findById($ownerId)
                : $this->players->findById($ownerId);
        }

        $name = is_array($item) ? (string) ($item['name'] ?? '') : '';
        $title = $name !== ''
            ? $this->t('admin.owned_items.view_title', ['name' => $name, 'id' => (string) $itemId])
            : $this->t('admin.owned_items.view_title_id', ['id' => (string) $itemId]);
        $data = [
            'title' => $title,
            'pageLead' => $this->t('admin.owned_items.lead'),
            'itemId' => $itemId,
            'item' => $item,
            'owner' => $owner,
            'ownerKind' => $ownerKind,
            'itemLogs' => $itemLogs,
            'activeTab' => $tab,
        ];

        if ($this->wantsTabPartial()) {
            if ($tab !== 'logs') {
                return Response::notFound();
            }

            return $this->adminFragment('components/owned-item-logs.twig', $data);
        }

        return $this->adminView('characters', 'pages/owned-item.twig', $data);
    }

    /**
     * @param list<array{id: string, label: string, columns: list<string>, dateColumn: string|null, itemColumns?: list<string>, rows: list<array<string, mixed>>}> $groups
     * @return list<array{id: string, label: string, columns: list<array{key: string, label: string}>, dateColumns: list<string>, itemColumns: list<string>, rows: list<array<string, mixed>>}>
     */
    private function decorateLogs(array $groups): array
    {
        $decorated = [];

        foreach ($groups as $group) {
            $decorated[] = [
                'id' => $group['id'],
                'label' => $group['label'],
                'columns' => $this->columnLabels($group['columns']),
                'dateColumns' => $this->dateColumns($group['columns']),
                'itemColumns' => $group['itemColumns'] ?? [],
                'rows' => $group['rows'],
            ];
        }

        return $decorated;
    }

    /**
     * @param list<string> $columns
     * @return list<array{key: string, label: string}>
     */
    private function columnLabels(array $columns): array
    {
        $labels = [];

        foreach ($columns as $column) {
            $key = 'admin.logs.columns.' . $column;
            $labels[] = [
                'key' => $column,
                'label' => $this->translator->has($key) ? $this->t($key) : $column,
            ];
        }

        return $labels;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function dateColumns(array $columns): array
    {
        $known = ['time', 'date', 'login_time', 'logout_time', 'start_time', 'end_time', 'first_seen', 'last_seen'];

        return array_values(array_intersect($columns, $known));
    }
}
