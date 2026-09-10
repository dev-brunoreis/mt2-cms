<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Theme\ThemeEngine;

class AdminItemShopController extends AdminController
{
    private const PER_PAGE = 20;

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private ItemShopCategoryRepository $categories,
        private ItemShopProductRepository $products,
        private ItemShopOrderRepository $orders,
        private GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function productsIndex(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $categoryFilter = $categoryId > 0 ? $categoryId : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->products->countForAdmin($query, $categoryFilter);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $rows = $this->products->listForAdmin($page, self::PER_PAGE, $query, $categoryFilter);

        return $this->adminView('item-shop', 'pages/item-shop-products.twig', [
            'title' => $this->t('admin.item_shop.products.title'),
            'pageLead' => $this->t('admin.item_shop.products.lead'),
            'headerHref' => '/admin/item-shop/new',
            'headerActionLabel' => $this->t('admin.item_shop.products.create'),
            'products' => $this->enrichProducts($rows),
            'categories' => $this->categories->listAllForSelect(),
            'query' => $q,
            'categoryId' => $categoryFilter ?? 0,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function productsCreate(): Response
    {
        return $this->productFormView($this->productPrefill());
    }

    public function productsStore(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/new');
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $product = $this->products->create($input);
            $this->flash('success', $this->t('admin.item_shop.products.created'));

            return $this->redirect('/admin/item-shop/' . $product['id']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->productFormView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function productsEdit(string $id): Response
    {
        $product = $this->products->findById((int) $id);

        if ($product === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect('/admin/item-shop');
        }

        return $this->productFormView($product, null, 200, true);
    }

    public function productsUpdate(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $productId = (int) $id;
        $existing = $this->products->findById($productId);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect('/admin/item-shop');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/' . $productId);
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $this->products->update($productId, $input);
            $this->flash('success', $this->t('admin.item_shop.products.updated'));

            return $this->redirect('/admin/item-shop/' . $productId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->productFormView(array_merge($existing, $input), $this->t($e->getMessage()), 422, true);
        }
    }

    public function productsDestroy(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop');
        }

        if (!$this->products->delete((int) $id)) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));
        } else {
            $this->flash('success', $this->t('admin.item_shop.products.deleted'));
        }

        return $this->redirect('/admin/item-shop');
    }

    public function categoriesIndex(): Response
    {
        return $this->categoryWorkspace(null);
    }

    public function categoriesCreate(): Response
    {
        $parentId = (int) ($_GET['parent_id'] ?? 0);

        return $this->categoryWorkspace(null, $this->categoryPrefill($parentId > 0 ? $parentId : null), false);
    }

    public function categoriesStore(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories/new');
        }

        $input = $this->categoryInput();

        try {
            $category = $this->categories->create($input);
            $this->flash('success', $this->t('admin.item_shop.categories.created'));

            return $this->redirect('/admin/item-shop/categories/' . $category['id']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->categoryWorkspace(null, $input, false, $this->t($e->getMessage()), 422);
        }
    }

    public function categoriesEdit(string $id): Response
    {
        $category = $this->categories->findById((int) $id);

        if ($category === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect('/admin/item-shop/categories');
        }

        return $this->categoryWorkspace($category, $category, true);
    }

    public function categoriesUpdate(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $existing = $this->categories->findById($categoryId);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect('/admin/item-shop/categories');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId);
        }

        $input = $this->categoryInput();

        try {
            $updated = $this->categories->update($categoryId, $input);
            $this->flash('success', $this->t('admin.item_shop.categories.updated'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->categoryWorkspace(
                $existing,
                array_merge($existing, $input),
                true,
                $this->t($e->getMessage()),
                422,
            );
        }
    }

    public function categoriesDestroy(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories');
        }

        try {
            if (!$this->categories->delete((int) $id)) {
                $this->flash('error', $this->t('admin.item_shop.categories.not_found'));
            } else {
                $this->flash('success', $this->t('admin.item_shop.categories.deleted'));
            }
        } catch (\RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/item-shop/categories');
    }

    public function categoriesMove(): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            return Response::json(['ok' => false, 'error' => $this->t('auth.invalid_csrf')], 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $parentRaw = $_POST['parent_id'] ?? null;
        $parentId = ($parentRaw === null || $parentRaw === '' || (int) $parentRaw === 0)
            ? null
            : (int) $parentRaw;
        $position = (int) ($_POST['position'] ?? 0);

        try {
            $this->categories->move($id, $parentId, $position);

            return Response::json(['ok' => true]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return Response::json(['ok' => false, 'error' => $this->t($e->getMessage())], 422);
        }
    }

    public function ordersIndex(): Response
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $query = $q !== '' ? $q : null;
        $status = (string) ($_GET['status'] ?? '');
        $statusFilter = in_array($status, ['pending', 'completed', 'failed'], true) ? $status : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->orders->countForAdmin($query, $statusFilter);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $rows = $this->orders->listForAdmin($page, self::PER_PAGE, $query, $statusFilter);

        return $this->adminView('item-shop-orders', 'pages/item-shop-orders.twig', [
            'title' => $this->t('admin.item_shop.orders.title'),
            'pageLead' => $this->t('admin.item_shop.orders.lead'),
            'orders' => $this->enrichOrders($rows),
            'query' => $q,
            'status' => $statusFilter ?? '',
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @param array<string, mixed>|null $selected
     * @param array<string, mixed>|null $form
     */
    private function categoryWorkspace(
        ?array $selected,
        ?array $form = null,
        bool $isEdit = false,
        ?string $error = null,
        int $status = 200,
    ): Response {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $isCreating = $form !== null && !$isEdit;
        $showForm = $selected !== null || $isCreating;

        if ($form === null && $selected !== null) {
            $form = $selected;
            $isEdit = true;
            $showForm = true;
        }

        $data = [
            'title' => $this->t('admin.item_shop.categories.title'),
            'pageLead' => $this->t('admin.item_shop.categories.lead'),
            'categoryTree' => $this->categories->treeForAdmin(),
            'parentOptions' => $this->categories->listAllForSelect(),
            'selectedCategory' => $selected,
            'category' => $form,
            'isEdit' => $isEdit,
            'showForm' => $showForm,
            'error' => $error,
            'moveUrl' => '/admin/item-shop/categories/move',
        ];

        if ($showForm) {
            $data['formId'] = 'admin-item-shop-category-form';
            $data['saveLabel'] = $this->t($isEdit ? 'admin.save' : 'admin.item_shop.categories.create');
        } else {
            $data['headerHref'] = '/admin/item-shop/categories/new';
            $data['headerActionLabel'] = $this->t('admin.item_shop.categories.add_root');
        }

        return $this->adminView('item-shop-categories', 'pages/item-shop-categories.twig', $data, $status);
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productFormView(array $product, ?string $error = null, int $status = 200, bool $isEdit = false): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $vnum = (int) ($product['vnum'] ?? 0);
        $itemName = $vnum > 0 ? $this->itemName($vnum) : '';

        return $this->adminView('item-shop', 'pages/item-shop-product-form.twig', [
            'title' => $this->t($isEdit ? 'admin.item_shop.products.edit_title' : 'admin.item_shop.products.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.item_shop.products.edit_lead' : 'admin.item_shop.products.create_lead'),
            'formId' => 'admin-item-shop-product-form',
            'saveLabel' => $this->t($isEdit ? 'admin.save' : 'admin.item_shop.products.create'),
            'product' => $product,
            'categories' => $this->categories->listAllForSelect(),
            'itemName' => $itemName,
            'isEdit' => $isEdit,
            'error' => $error,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPrefill(): array
    {
        return [
            'category_id' => 0,
            'vnum' => '',
            'count' => 1,
            'price' => 1,
            'socket0' => 0,
            'socket1' => 0,
            'socket2' => 0,
            'enabled' => 1,
            'sort_order' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPrefill(?int $parentId = null): array
    {
        return [
            'parent_id' => $parentId,
            'name' => '',
            'slug' => '',
            'sort_order' => 0,
            'enabled' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productInput(): array
    {
        return [
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'vnum' => (int) ($_POST['vnum'] ?? 0),
            'count' => (int) ($_POST['count'] ?? 1),
            'price' => (int) ($_POST['price'] ?? 0),
            'socket0' => (int) ($_POST['socket0'] ?? 0),
            'socket1' => (int) ($_POST['socket1'] ?? 0),
            'socket2' => (int) ($_POST['socket2'] ?? 0),
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryInput(): array
    {
        $parentRaw = $_POST['parent_id'] ?? '';

        return [
            'parent_id' => ($parentRaw === '' || (int) $parentRaw === 0) ? null : (int) $parentRaw,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];
    }

    private function assertKnownVnum(int $vnum): void
    {
        if ($vnum < 1 || $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum) === null) {
            throw new \InvalidArgumentException('admin.item_shop.products.vnum_not_found');
        }
    }

    private function itemName(int $vnum): string
    {
        $row = $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum);

        if ($row === null) {
            return '';
        }

        $locale = trim((string) ($row['locale_name'] ?? ''));

        return $locale !== '' ? $locale : trim((string) ($row['name'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichProducts(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['item_name'] = $this->itemName((int) $row['vnum']);
        }

        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichOrders(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['item_name'] = $this->itemName((int) $row['vnum']);
        }

        unset($row);

        return $rows;
    }
}
