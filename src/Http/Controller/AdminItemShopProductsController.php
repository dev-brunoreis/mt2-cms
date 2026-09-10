<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminItemShopProductsController extends AdminItemShopBaseController
{
    public function productsIndex(): Response
    {
        $spec = $this->productsGridSpec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->products->countForGrid($q),
            fn ($q) => $this->enrichProducts($this->products->listForGrid($q)),
        );

        return $this->adminView('store', 'pages/item-shop-products.twig', [
            'title' => $this->t('admin.item_shop.products.title'),
            'pageLead' => $this->t('admin.item_shop.products.lead'),
            'headerHref' => '/admin/store/products/new',
            'headerActionLabel' => $this->t('admin.item_shop.products.create'),
            'grid' => $grid,
        ]);
    }

    public function mass(): Response
    {
        return $this->runMassActions(
            $this->productsGridSpec(),
            '/admin/store/products',
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
        'store',
        );
    }

    public function productsCreate(): Response
    {
        return $this->productFormView($this->productPrefill());
    }

    public function productsStore(): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/store/products/new');
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $product = $this->products->create($input);
            $this->audit('item_shop.product.create', 'item_shop_product', (int) $product['id']);
            $this->flash('success', $this->t('admin.item_shop.products.created'));

            return $this->redirect('/admin/store/products/' . $product['id']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->productFormView($input, $this->t($e->getMessage()), 422);
        }
    }

    public function productsEdit(string $id): Response
    {
        $product = $this->products->findById((int) $id);

        if ($product === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect('/admin/store/products');
        }

        return $this->productFormView($product, null, 200, true);
    }

    public function productsUpdate(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        $productId = (int) $id;
        $existing = $this->products->findById($productId);

        if ($existing === null) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));

            return $this->redirect('/admin/store/products');
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/store/products/' . $productId);
        }

        $input = $this->productInput();

        try {
            $this->assertKnownVnum((int) $input['vnum']);
            $this->products->update($productId, $input);
            $this->audit('item_shop.product.update', 'item_shop_product', $productId);
            $this->flash('success', $this->t('admin.item_shop.products.updated'));

            return $this->redirect('/admin/store/products/' . $productId);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->productFormView(array_merge($existing, $input), $this->t($e->getMessage()), 422, true);
        }
    }

    public function productsDestroy(string $id): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        if (!$this->assertCsrf()) {
            $this->flash('error', $this->t('auth.invalid_csrf'));

            return $this->redirect('/admin/store/products');
        }

        if (!$this->products->delete((int) $id)) {
            $this->flash('error', $this->t('admin.item_shop.products.not_found'));
        } else {
            $this->audit('item_shop.product.delete', 'item_shop_product', (int) $id);
            $this->flash('success', $this->t('admin.item_shop.products.deleted'));
        }

        return $this->redirect('/admin/store/products');
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

        return $this->adminView('store', 'pages/item-shop-product-form.twig', [
            'title' => $this->t($isEdit ? 'admin.item_shop.products.edit_title' : 'admin.item_shop.products.create_title'),
            'pageLead' => $this->t($isEdit ? 'admin.item_shop.products.edit_lead' : 'admin.item_shop.products.create_lead'),
            'formId' => 'admin-item-shop-product-form',
            'saveLabel' => $this->t('admin.save'),
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
