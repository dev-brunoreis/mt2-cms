<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;

class AdminItemShopCategoriesController extends AdminItemShopBaseController
{
    public function categoriesIndex(): Response
    {
        return $this->redirect(AdminPaths::store());
    }

    public function categoriesCreate(): Response
    {
        $parentId = (int) ($_GET['parent_id'] ?? 0);

        return $this->redirect(AdminPaths::storeCategoryNew($parentId > 0 ? $parentId : null));
    }

    public function categoriesStore(): Response
    {
        if ($redirect = $this->requireAdminResource('store/categories/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::storeCategoryNew());
        }

        $input = $this->categoryInput();

        try {
            $category = $this->categories->create($input);
            $this->audit('item_shop.category.create', 'item_shop_category', (int) $category['id']);
            $this->flash('success', $this->t('admin.item_shop.categories.created'));

            return $this->redirect(AdminPaths::storeCategoryEdit((int) $category['id']));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->categoryWorkspace(null, $input, false, $this->t($e->getMessage()), 422);
        }
    }

    public function categoriesEdit(string $id): Response
    {
        $category = $this->categories->findById((int) $id);

        if ($category === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect(AdminPaths::store());
        }

        $panel = $this->categoryPanel();

        return $this->redirect(AdminPaths::storeCategoryEdit((int) $id, $panel === 'products' ? null : $panel));
    }

    public function categoriesUpdate(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/categories/edit')) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $existing = $this->categories->findById($categoryId);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect(AdminPaths::store());
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'dados'));
        }

        $input = $this->categoryInput();

        try {
            $this->categories->update($categoryId, $input);
            $this->auditChange('item_shop.category.update', 'item_shop_category', $categoryId, [
                'parent_id' => $existing['parent_id'],
                'name' => $existing['name'],
                'slug' => $existing['slug'],
                'sort_order' => $existing['sort_order'],
                'enabled' => $existing['enabled'],
            ], $input);
            $this->flash('success', $this->t('admin.item_shop.categories.updated'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'dados'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->categoryWorkspace(
                $existing,
                array_merge($existing, $input),
                true,
                $this->t($e->getMessage()),
                422,
                'dados',
            );
        }
    }

    public function categoriesDestroy(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/categories/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::store());
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

        return $this->redirect(AdminPaths::store());
    }

    public function categoriesMove(): Response
    {
        if ($redirect = $this->requireAdminResource('store/categories/move')) {
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
            $existing = $this->categories->findById($id);
            $this->categories->move($id, $parentId, $position);
            $this->auditChange('item_shop.category.move', 'item_shop_category', $id, [
                'parent_id' => $existing['parent_id'] ?? null,
                'sort_order' => $existing['sort_order'] ?? null,
            ], [
                'parent_id' => $parentId,
                'sort_order' => $position,
            ]);

            return Response::json(['ok' => true]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return Response::json(['ok' => false, 'error' => $this->t($e->getMessage())], 422);
        }
    }

    public function categoriesItemSearch(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        $categoryId = (int) $id;

        if ($this->categories->findById($categoryId) === null) {
            return Response::json(['ok' => false, 'error' => $this->t('admin.item_shop.categories.not_found')], 404);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $assignedRows = $this->enrichProducts($this->products->listByCategoryId($categoryId));
        $assignedVnums = [];
        $assigned = [];

        foreach ($assignedRows as $product) {
            $vnum = (int) ($product['vnum'] ?? 0);

            if ($vnum < 1) {
                continue;
            }

            $assignedVnums[] = $vnum;
            $name = trim((string) ($product['item_name'] ?? ''));

            if ($name === '') {
                $name = (string) $vnum;
            }

            if ($q !== '' && !$this->productMatchesQuery($vnum, $name, $q)) {
                continue;
            }

            $assigned[] = [
                'id' => (int) $product['id'],
                'vnum' => $vnum,
                'name' => $name,
                'price' => (int) ($product['price'] ?? 1),
                'count' => (int) ($product['count'] ?? 1),
            ];
        }

        $result = $this->protos->page(
            ProtoSchemas::KIND_ITEM,
            $page,
            $perPage,
            $q !== '' ? $q : null,
            $assignedVnums,
        );

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
            ];
        }

        $total = (int) $result['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::json([
            'ok' => true,
            'assigned' => $assigned,
            'items' => $items,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function categoriesAddProducts(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/create')) {
            return $redirect;
        }

        $categoryId = (int) $id;

        if ($this->categories->findById($categoryId) === null) {
            $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

            return $this->redirect(AdminPaths::store());
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
        }

        $shownRaw = $_POST['shown_ids'] ?? [];
        $selectedRaw = $_POST['selected'] ?? [];
        $itemsRaw = $_POST['items'] ?? [];

        if (!is_array($shownRaw)) {
            $shownRaw = [];
        }

        if (!is_array($selectedRaw)) {
            $selectedRaw = [];
        }

        if (!is_array($itemsRaw)) {
            $itemsRaw = [];
        }

        $shownIds = [];

        foreach ($shownRaw as $shownId) {
            $shownIds[] = (int) $shownId;
        }

        $checked = [];

        foreach ($selectedRaw as $vnumRaw) {
            $vnum = (int) $vnumRaw;

            if ($vnum < 1) {
                continue;
            }

            $row = $itemsRaw[(string) $vnum] ?? $itemsRaw[$vnum] ?? null;

            if (!is_array($row)) {
                $row = [];
            }

            $productId = (int) ($row['id'] ?? 0);
            $price = (int) ($row['price'] ?? 0);
            $count = (int) ($row['count'] ?? 1);

            if ($price < 1) {
                $this->flash('error', $this->t('admin.item_shop.products.invalid_price'));

                return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
            }

            if ($productId < 1) {
                try {
                    $this->assertKnownVnum($vnum);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }

            $checked[] = [
                'id' => $productId,
                'vnum' => $vnum,
                'price' => $price,
                'count' => $count,
            ];
        }

        try {
            $assigned = [];

            foreach ($this->products->listByCategoryId($categoryId) as $product) {
                $assigned[] = [
                    'id' => (int) ($product['id'] ?? 0),
                    'vnum' => (int) ($product['vnum'] ?? 0),
                    'price' => (int) ($product['price'] ?? 0),
                    'count' => (int) ($product['count'] ?? 1),
                ];
            }

            $this->products->syncCategoryProducts($categoryId, $shownIds, $checked);
            $this->auditChange(
                'item_shop.category.products_sync',
                'item_shop_category',
                $categoryId,
                ['products' => $assigned],
                ['products' => $checked],
            );
            $this->flash('success', $this->t('admin.item_shop.categories.products_saved'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
    }

    public function categoriesUpdateProduct(string $id, string $productId): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/edit')) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $pid = (int) $productId;

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
        }

        $product = $this->products->findById($pid);

        if ($product === null || (int) $product['category_id'] !== $categoryId) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
        }

        try {
            $this->products->updatePriceAndCount(
                $pid,
                (int) ($_POST['price'] ?? 0),
                (int) ($_POST['count'] ?? 1),
            );
            $this->auditChange('item_shop.product.update', 'item_shop_product', $pid, [
                'price' => $product['price'],
                'count' => $product['count'],
            ], [
                'price' => (int) ($_POST['price'] ?? 0),
                'count' => (int) ($_POST['count'] ?? 1),
            ]);
            $this->flash('success', $this->t('admin.item_shop.products.updated'));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));
        }

        return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
    }

    public function categoriesRemoveProduct(string $id, string $productId): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/delete')) {
            return $redirect;
        }

        $categoryId = (int) $id;
        $pid = (int) $productId;

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
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

        return $this->redirect(AdminPaths::storeCategoryEdit($categoryId, 'products'));
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
        ?string $categoryTab = null,
    ): Response {
        $data = $this->categoryWorkspaceData($selected, $form, $isEdit, $error, $categoryTab);

        return $this->storeHubView(
            'categories',
            [
                'template' => 'pages/store-categories-partial.twig',
                'data' => $data,
            ],
            $this->categoriesHubHeader($data),
            $status,
        );
    }

    private function productMatchesQuery(int $vnum, string $name, string $query): bool
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return true;
        }

        return str_contains((string) $vnum, $needle)
            || str_contains(mb_strtolower($name), $needle);
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
