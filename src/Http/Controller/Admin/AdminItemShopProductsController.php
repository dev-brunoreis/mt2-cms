<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Http\Response;

class AdminItemShopProductsController extends AdminItemShopBaseController
{
    public function productsIndex(): Response
    {
        return $this->redirect(AdminPaths::store());
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->productsGridSpec(),
            AdminPaths::store(),
            [
                'enable' => function (int $id): bool {
                    $product = $this->products->findById($id);

                    return $product !== null && $this->updateProductEnabled($product, 1);
                },
                'disable' => function (int $id): bool {
                    $product = $this->products->findById($id);

                    return $product !== null && $this->updateProductEnabled($product, 0);
                },
                'delete' => fn (int $id): bool => $this->products->delete($id),
            ],
            'item_shop_product',
            'admin.item_shop.products.mass_done',
            'store/products/mass',
        );
    }

    public function productsCreate(): Response
    {
        return $this->redirect(AdminPaths::store());
    }

    public function productsStore(): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/create')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::store());
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $product = $this->products->create($input);
            $this->audit('item_shop.product.create', 'item_shop_product', (int) $product['id']);
            $this->flash('success', $this->t('admin.item_shop.products.created'));

            return $this->redirect($this->productHubPath($product));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));

            return $this->redirect(AdminPaths::store());
        }
    }

    public function productsEdit(string $id): Response
    {
        $product = $this->products->findById((int) $id);

        if ($product === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect(AdminPaths::store());
        }

        return $this->redirect($this->productHubPath($product));
    }

    public function productsUpdate(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/edit')) {
            return $redirect;
        }

        $productId = (int) $id;
        $existing = $this->products->findById($productId);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect(AdminPaths::store());
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect($this->productHubPath($existing));
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $this->products->update($productId, $input);
            $this->auditChange('item_shop.product.update', 'item_shop_product', $productId, [
                'category_id' => $existing['category_id'],
                'vnum' => $existing['vnum'],
                'count' => $existing['count'],
                'price' => $existing['price'],
                'socket0' => $existing['socket0'],
                'socket1' => $existing['socket1'],
                'socket2' => $existing['socket2'],
                'enabled' => $existing['enabled'],
                'sort_order' => $existing['sort_order'],
            ], $input);
            $this->flash('success', $this->t('admin.item_shop.products.updated'));

            return $this->redirect($this->productHubPath($existing));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('error', $this->t($e->getMessage()));

            return $this->redirect($this->productHubPath($existing));
        }
    }

    public function productsDestroy(string $id): Response
    {
        if ($redirect = $this->requireAdminResource('store/products/delete')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect(AdminPaths::store());
        }

        if (!$this->products->delete((int) $id)) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));
        } else {
            $this->audit('item_shop.product.delete', 'item_shop_product', (int) $id);
            $this->flash('success', $this->t('admin.item_shop.products.deleted'));
        }

        return $this->redirect(AdminPaths::store());
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productHubPath(array $product): string
    {
        $categoryId = (int) ($product['category_id'] ?? 0);

        if ($categoryId > 0) {
            return AdminPaths::storeCategoryEdit($categoryId, 'products');
        }

        return AdminPaths::store();
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
}
