<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminShopsController extends AdminController
{
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
        AclService $acl,
        AdminAuditService $auditLog,
        private ShopRepository $shops,
        private GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        $spec = $this->shops->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->shops->countForGrid($q),
            fn ($q) => $this->shops->listForGrid($q),
        );

        return $this->adminView('shops', 'pages/shops.twig', [
            'title' => $this->t('admin.shops.title'),
            'pageLead' => $this->t('admin.shops.lead'),
            'headerHref' => '/admin/game-data/shops/new',
            'headerActionLabel' => $this->t('admin.shops.create'),
            'grid' => $grid,
        ]);
    }

    public function create(): Response
    {
        return $this->formView(['vnum' => '', 'name' => '', 'npc_vnum' => 0], null, 200, false);
    }

    public function store(): Response
    {
        if ($redirect = $this->requireAdminSection('shops')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/shops/new');
        }

        $input = $this->formInput();

        try {
            $shop = $this->shops->create($input);
            $this->audit('shop.create', 'shop', (int) $shop['vnum']);
            $this->flash('success', $this->t('admin.shops.created'));

            return $this->redirect('/admin/game-data/shops/' . $shop['vnum']);
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

            return $this->redirect('/admin/game-data/shops');
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
        if ($redirect = $this->requireAdminSection('shops')) {
            return $redirect;
        }

        $vnum = (int) $id;
        $shop = $this->shops->findForAdmin($vnum);

        if ($shop === null) {
            $this->flash('error', $this->t('admin.shops.not_found'));

            return $this->redirect('/admin/game-data/shops');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/shops/' . $vnum);
        }

        $input = $this->formInput();

        try {
            $this->shops->update($vnum, $input);
            $this->audit('shop.update', 'shop', $vnum);
            $this->flash('success', $this->t('admin.shops.updated'));

            return $this->redirect('/admin/game-data/shops/' . $vnum);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->formView(array_merge($shop, $input), $this->t($e->getMessage()), 422, true);
        }
    }

    public function destroy(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('shops')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/shops');
        }

        if (!$this->shops->delete((int) $id)) {
            $this->flash('error', $this->t('admin.shops.not_found'));
        } else {
            $this->audit('shop.delete', 'shop', (int) $id);
            $this->flash('success', $this->t('admin.shops.deleted'));
        }

        return $this->redirect('/admin/game-data/shops');
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
        if ($redirect = $this->requireAdminSection('shops')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/game-data/shops/' . $shopVnum . '?tab=items');
        }

        $itemVnum = (int) ($_POST['item_vnum'] ?? 0);
        $count = max(1, (int) ($_POST['count'] ?? 1));

        try {
            if ($action === 'add') {
                if ($this->protos->find(ProtoSchemas::KIND_ITEM, $itemVnum) === null) {
                    throw new \InvalidArgumentException('admin.shops.item_not_found');
                }

                $this->shops->addItem($shopVnum, $itemVnum, $count);
                $this->audit('shop.item_add', 'shop', $shopVnum, ['item_vnum' => $itemVnum]);
                $this->flash('success', $this->t('admin.shops.item_added'));
            } else {
                if (!$this->shops->removeItem($shopVnum, $itemVnum, $count)) {
                    throw new \InvalidArgumentException('admin.shops.item_not_found');
                }

                $this->audit('shop.item_remove', 'shop', $shopVnum, ['item_vnum' => $itemVnum]);
                $this->flash('success', $this->t('admin.shops.item_removed'));
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/game-data/shops/' . $shopVnum . '?tab=items');
    }
}
