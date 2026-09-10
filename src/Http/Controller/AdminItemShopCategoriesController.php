<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;

class AdminItemShopCategoriesController extends AdminItemShopBaseController
{
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
            $this->audit('item_shop.category.create', 'item_shop_category', (int) $category['id']);
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
            $this->categories->update($categoryId, $input);
            $this->audit('item_shop.category.update', 'item_shop_category', $categoryId);
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
                $this->audit('item_shop.category.delete', 'item_shop_category', (int) $id);
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
            $this->audit('item_shop.category.move', 'item_shop_category', $id);

            return Response::json(['ok' => true]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return Response::json(['ok' => false, 'error' => $this->t($e->getMessage())], 422);
        }
    }

    public function categoriesItemSearch(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $categoryId = (int) $id;

        if ($this->categories->findById($categoryId) === null) {
            return Response::json(['ok' => false, 'error' => $this->t('admin.item_shop.categories.not_found')], 404);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $result = $this->protos->page(ProtoSchemas::KIND_ITEM, $page, $perPage, $q !== '' ? $q : null);
        $existing = array_flip($this->products->vnumsInCategory($categoryId));

        $items = [];

        foreach ($result['rows'] as $row) {
            $vnum = (int) ($row['vnum'] ?? 0);

            if ($vnum < 1) {
                continue;
            }

            $locale = trim((string) ($row['locale_name'] ?? ''));
            $name = $locale !== '' ? $locale : trim((string) ($row['name'] ?? ''));

            $items[] = [
                'vnum' => $vnum,
                'name' => $name !== '' ? $name : (string) $vnum,
                'in_category' => isset($existing[$vnum]),
            ];
        }

        $total = (int) $result['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::json([
            'ok' => true,
            'items' => $items,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function categoriesAddProducts(string $id): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $categoryId = (int) $id;

        if ($this->categories->findById($categoryId) === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect('/admin/item-shop/categories');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
        }

        $rawItems = $_POST['items'] ?? [];

        if (!is_array($rawItems)) {
            $rawItems = [];
        }

        $items = [];

        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vnum = (int) ($row['vnum'] ?? 0);
            $price = (int) ($row['price'] ?? 0);
            $count = (int) ($row['count'] ?? 1);

            if ($vnum < 1) {
                continue;
            }

            try {
                $this->assertKnownVnum($vnum);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $items[] = [
                'vnum' => $vnum,
                'price' => $price,
                'count' => $count,
            ];
        }

        try {
            $created = $this->products->createManyForCategory($categoryId, $items);
            $this->audit('item_shop.category.products_add', 'item_shop_category', $categoryId, [
                'count' => count($created),
            ]);
            $this->flash('success', $this->t('admin.item_shop.categories.products_added', ['count' => count($created)]));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
    }

    public function categoriesUpdateProduct(string $id, string $productId): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $pid = (int) $productId;

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
        }

        $product = $this->products->findById($pid);

        if ($product === null || (int) $product['category_id'] !== $categoryId) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
        }

        try {
            $this->products->updatePriceAndCount(
                $pid,
                (int) ($_POST['price'] ?? 0),
                (int) ($_POST['count'] ?? 1),
            );
            $this->audit('item_shop.product.update', 'item_shop_product', $pid);
            $this->flash('success', $this->t('admin.item_shop.products.updated'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
    }

    public function categoriesRemoveProduct(string $id, string $productId): Response
    {
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $pid = (int) $productId;

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
        }

        $product = $this->products->findById($pid);

        if ($product === null || (int) $product['category_id'] !== $categoryId) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));
        } elseif (!$this->products->delete($pid)) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));
        } else {
            $this->audit('item_shop.product.delete', 'item_shop_product', $pid);
            $this->flash('success', $this->t('admin.item_shop.products.deleted'));
        }

        return $this->redirect('/admin/item-shop/categories/' . $categoryId . '?tab=products');
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
            'activeTab' => $isEdit
                ? $this->requestedTab(['dados', 'products'], 'products')
                : 'dados',
            'categoryProducts' => [],
            'itemSearchUrl' => null,
            'addProductsUrl' => null,
        ];

        if ($isEdit && $selected !== null) {
            $categoryId = (int) $selected['id'];
            $data['categoryProducts'] = $this->enrichProducts($this->products->listByCategoryId($categoryId));
            $data['itemSearchUrl'] = '/admin/item-shop/categories/' . $categoryId . '/item-search';
            $data['addProductsUrl'] = '/admin/item-shop/categories/' . $categoryId . '/products';
        }

        if ($showForm && (!$isEdit || ($data['activeTab'] ?? 'dados') === 'dados')) {
            $data['formId'] = 'admin-item-shop-category-form';
            $data['saveLabel'] = $this->t('admin.save');
        } elseif (!$showForm) {
            $data['headerHref'] = '/admin/item-shop/categories/new';
            $data['headerActionLabel'] = $this->t('admin.item_shop.categories.add_root');
        }

        return $this->adminView('item-shop-categories', 'pages/item-shop-categories.twig', $data, $status);
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
}
