<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Theme\ThemeEngine;

class AdminShopsController extends AdminController
{
    private const PER_PAGE = 20;

    /** @var array<string, string> */
    private const TAB_TEMPLATES = [
        'items' => 'components/shop-items.twig',
    ];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private ShopRepository $shops,
        private GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->shops->countForAdmin($query);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->adminView('shops', 'pages/shops.twig', [
            'title' => $this->t('admin.shops.title'),
            'pageLead' => $this->t('admin.shops.lead'),
            'headerHref' => '/admin/shops/new',
            'headerActionLabel' => $this->t('admin.shops.create'),
            'shops' => $this->shops->listForAdmin($page, self::PER_PAGE, $query),
            'query' => $q,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function create(): Response
    {
        return $this->formView(['vnum' => '', 'name' => '', 'npc_vnum' => 0], null, 200, false);
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/shops/new');
        }

        $input = $this->formInput();

        try {
            $shop = $this->shops->create($input);
            $this->flash('success', $this->t('admin.shops.created'));

            return $this->redirect('/admin/shops/' . $shop['vnum']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView($input, $this->t($e->getMessage()), 422, false);
        }
    }

    public function edit(string $id): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $shop = $this->shops->findForAdmin((int) $id);

        if ($shop === null) {
            $this->flash('error', $this->t('admin.shops.not_found'));

            return $this->redirect('/admin/shops');
        }

        $tab = $this->requestedTab(['dados', 'items'], 'dados');
        $data = [
            'title' => $this->t('admin.shops.edit_title', ['name' => (string) $shop['name']]),
            'pageLead' => $this->t('admin.shops.edit_lead'),
            'formId' => 'admin-shop-form',
            'saveLabel' => $this->t('admin.save'),
            'shop' => $shop,
            'isEdit' => true,
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

        return $this->adminView('shops', 'pages/shop-form.twig', $data);
    }

    public function update(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $vnum = (int) $id;
        $shop = $this->shops->findForAdmin($vnum);

        if ($shop === null) {
            $this->flash('error', $this->t('admin.shops.not_found'));

            return $this->redirect('/admin/shops');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/shops/' . $vnum);
        }

        $input = $this->formInput();

        try {
            $this->shops->update($vnum, $input);
            $this->flash('success', $this->t('admin.shops.updated'));

            return $this->redirect('/admin/shops/' . $vnum);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($shop, $input), $this->t($e->getMessage()), 422, true);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/shops');
        }

        if (!$this->shops->delete((int) $id)) {
            $this->flash('error', $this->t('admin.shops.not_found'));
        } else {
            $this->flash('success', $this->t('admin.shops.deleted'));
        }

        return $this->redirect('/admin/shops');
    }

    public function addItem(string $id): Response
    {
        return $this->itemAction((int) $id, 'add');
    }

    public function removeItem(string $id): Response
    {
        return $this->itemAction((int) $id, 'remove');
    }

    /**
     * @param array<string, mixed> $shop
     */
    private function formView(array $shop, ?string $error, int $status, bool $isEdit): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        return $this->adminView('shops', 'pages/shop-form.twig', [
            'title' => $this->t($isEdit ? 'admin.shops.edit_title' : 'admin.shops.create_title', ['name' => (string) ($shop['name'] ?? '')]),
            'pageLead' => $this->t($isEdit ? 'admin.shops.edit_lead' : 'admin.shops.create_lead'),
            'formId' => 'admin-shop-form',
            'saveLabel' => $this->t('admin.save'),
            'shop' => $shop,
            'isEdit' => $isEdit,
            'activeTab' => 'dados',
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(): array
    {
        return [
            'vnum' => (int) ($_POST['vnum'] ?? 0),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'npc_vnum' => (int) ($_POST['npc_vnum'] ?? 0),
        ];
    }

    private function itemAction(int $shopVnum, string $action): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/shops/' . $shopVnum . '?tab=items');
        }

        $itemVnum = (int) ($_POST['item_vnum'] ?? 0);
        $count = max(1, (int) ($_POST['count'] ?? 1));

        try {
            if ($action === 'add') {
                if ($this->protos->find(ProtoSchemas::KIND_ITEM, $itemVnum) === null) {
                    throw new \InvalidArgumentException('admin.shops.item_not_found');
                }

                $this->shops->addItem($shopVnum, $itemVnum, $count);
                $this->flash('success', $this->t('admin.shops.item_added'));
            } else {
                if (!$this->shops->removeItem($shopVnum, $itemVnum, $count)) {
                    throw new \InvalidArgumentException('admin.shops.item_not_found');
                }

                $this->flash('success', $this->t('admin.shops.item_removed'));
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/shops/' . $shopVnum . '?tab=items');
    }
}
