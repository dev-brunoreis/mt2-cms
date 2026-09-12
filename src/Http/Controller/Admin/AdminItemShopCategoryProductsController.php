<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;

class AdminItemShopCategoryProductsController extends AdminItemShopBaseController
{
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

        $gridQuery = new GridQuery(
            $q !== '' ? $q : null,
            $page,
            $perPage,
            'vnum',
            'asc',
            [],
        );
        $total = $this->protos->countForGrid(ProtoSchemas::KIND_ITEM, $gridQuery, $assignedVnums);
        $resultRows = $this->protos->listForGrid(ProtoSchemas::KIND_ITEM, $gridQuery, $assignedVnums);

        $items = [];

        foreach ($resultRows as $row) {
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

    private function productMatchesQuery(int $vnum, string $name, string $query): bool
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return true;
        }

        return str_contains((string) $vnum, $needle)
            || str_contains(mb_strtolower($name), $needle);
    }
}
