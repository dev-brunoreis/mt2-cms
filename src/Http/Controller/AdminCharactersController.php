<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

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
use Mt2Cms\Theme\ThemeEngine;

class AdminCharactersController extends AdminController
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private PlayerRepository $players,
        private ItemRepository $items,
        private GuildRepository $guilds,
        private LogRepository $logs,
        private AccountRepository $accounts,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->players->countForAdmin($query);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('characters', 'pages/characters.twig', [
            'title' => $this->t('admin.characters.title'),
            'pageLead' => $this->t('admin.characters.lead'),
            'characters' => $this->players->listForAdmin($page, self::PER_PAGE, $query),
            'query' => $q,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function show(string $id): Response
    {
        $character = $this->players->findForAdmin((int) $id);

        if ($character === null) {
            $this->flash('error', $this->t('admin.characters.not_found'));

            return $this->redirect('/admin/characters');
        }

        $playerId = (int) $character['id'];
        $accountId = (int) ($character['account_id'] ?? 0);
        $name = (string) $character['name'];
        $characterItems = $this->items->forCharacter($playerId);
        $safebox = $accountId > 0 ? $this->items->safeboxForAccount($accountId) : null;

        return $this->adminView('characters', 'pages/character.twig', [
            'title' => $this->t('admin.characters.view_title', ['name' => $name]),
            'pageLead' => $this->t('admin.characters.view_lead'),
            'character' => $character,
            'characterLogs' => $this->decorateLogs($this->logs->listForCharacter($playerId, $name)),
            'characterItems' => $characterItems,
            'characterItemLayout' => InventoryLayout::forCharacter($characterItems),
            'safebox' => $safebox,
            'safeboxLayout' => $safebox !== null
                ? InventoryLayout::forAccount($safebox['items'], (int) $safebox['size'])
                : null,
            'guild' => $this->guilds->profileForPlayer($playerId),
            'marriage' => $this->players->findMarriageForPlayer($playerId),
        ]);
    }

    public function showOwnedItem(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $itemId = (int) $id;

        if ($itemId < 1) {
            $this->flash('error', $this->t('admin.owned_items.not_found'));

            return $this->redirect('/admin/characters');
        }

        $item = $this->items->findById($itemId);
        $itemLogs = $this->decorateLogs($this->logs->listForItem($itemId));

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

        return $this->adminView('characters', 'pages/owned-item.twig', [
            'title' => $title,
            'pageLead' => $this->t('admin.owned_items.lead'),
            'itemId' => $itemId,
            'item' => $item,
            'owner' => $owner,
            'ownerKind' => $ownerKind,
            'itemLogs' => $itemLogs,
        ]);
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
