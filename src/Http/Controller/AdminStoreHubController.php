<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Theme\ThemeEngine;

class AdminStoreHubController extends AdminController
{
    private const TABS = ['products', 'categories', 'orders'];

    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        private ItemShopProductRepository $products,
        private ItemShopCategoryRepository $categories,
        private ItemShopOrderRepository $orders,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    public function index(): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        $tab = $this->requestedTab(self::TABS, 'products');

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        $header = match ($tab) {
            'products' => [
                'headerHref' => AdminPaths::storeProductNew(),
                'headerActionLabel' => $this->t('admin.item_shop.products.create'),
            ],
            'categories' => [
                'headerHref' => AdminPaths::storeCategoryNew(),
                'headerActionLabel' => $this->t('admin.item_shop.categories.create'),
            ],
            default => [],
        };

        return $this->adminView('store', 'pages/store-hub.twig', array_merge([
            'title' => $this->t('admin.store.hub_title'),
            'pageLead' => $this->t('admin.store.hub_lead'),
            'activeTab' => $tab,
            'storeBaseUrl' => AdminPaths::store(),
            'initialPartial' => $this->partialPayload($tab),
        ], $header));
    }

    private function renderTabPartial(string $tab): Response
    {
        if (!in_array($tab, self::TABS, true)) {
            return new Response('', 404);
        }

        $payload = $this->partialPayload($tab);

        return $this->adminFragment($payload['template'], $payload['data']);
    }

    /**
     * @return array{template: string, data: array<string, mixed>}
     */
    private function partialPayload(string $tab): array
    {
        return match ($tab) {
            'categories' => [
                'template' => 'pages/store-categories-partial.twig',
                'data' => $this->categoriesPartialData(),
            ],
            'orders' => [
                'template' => 'pages/store-orders-partial.twig',
                'data' => ['grid' => $this->ordersGrid()],
            ],
            default => [
                'template' => 'pages/store-products-partial.twig',
                'data' => ['grid' => $this->productsGrid()],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function categoriesPartialData(): array
    {
        return [
            'categoryTree' => $this->categories->treeForAdmin(),
            'parentOptions' => $this->categories->listAllForSelect(),
            'selectedCategory' => null,
            'category' => null,
            'isEdit' => false,
            'showForm' => false,
            'error' => null,
            'moveUrl' => AdminPaths::storeCategories() . '/move',
            'activeTab' => 'dados',
            'categoryProducts' => [],
            'itemSearchUrl' => null,
            'addProductsUrl' => null,
            'headerHref' => AdminPaths::storeCategoryNew(),
            'headerActionLabel' => $this->t('admin.item_shop.categories.add_root'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productsGrid(): array
    {
        $spec = $this->products->gridDefinition()->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->products->countForGrid($q),
            fn ($q) => $this->products->listForGrid($q),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ordersGrid(): array
    {
        $spec = $this->orders->gridDefinition()->spec();
        $query = $this->gridQuery($spec);

        return GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->orders->countForGrid($q),
            fn ($q) => $this->orders->listForGrid($q),
        );
    }
}
