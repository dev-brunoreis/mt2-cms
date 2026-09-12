<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\Grid\Definitions\ItemShopProductsGrid;
use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridSpec;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Support\SelectOptions;
use Mt2Cms\Theme\ThemeEngine;

abstract class AdminItemShopBaseController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        AclService $acl,
        AdminAuditService $auditLog,
        protected ItemShopCategoryRepository $categories,
        protected ItemShopProductRepository $products,
        protected ItemShopOrderRepository $orders,
        protected GameProtoService $protos,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme, $auditLog, $acl);
    }

    protected function assertKnownVnum(int $vnum): void
    {
        if ($vnum < 1 || $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum) === null) {
            throw new \InvalidArgumentException('admin.item_shop.products.vnum_not_found');
        }
    }

    protected function itemName(int $vnum): string
    {
        $row = $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum);

        if ($row === null) {
            return '';
        }

        $locale = trim((string) ($row['locale_name'] ?? ''));

        return $locale !== '' ? $locale : trim((string) ($row['name'] ?? ''));
    }

    protected function productsGridSpec(): GridSpec
    {
        $categoryOptions = [];

        foreach ($this->categories->listAllForSelect() as $category) {
            $categoryOptions[(string) $category['id']] = (string) $category['name'];
        }

        $categoryOptions = SelectOptions::sortMap($categoryOptions);

        return ItemShopProductsGrid::definition()
            ->filterOptions('category_id', $categoryOptions, false)
            ->spec();
    }

    /**
     * @param array<string, mixed> $product
     */
    protected function updateProductEnabled(array $product, int $enabled): bool
    {
        try {
            $this->products->update((int) $product['id'], array_merge($product, ['enabled' => $enabled]));

            return true;
        } catch (\InvalidArgumentException | \RuntimeException) {
            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichProducts(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['item_name'] = $this->itemName((int) $row['vnum']);
            $row['enabled'] = (string) (int) ($row['enabled'] ?? 0);
        }

        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichOrders(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['item_name'] = $this->itemName((int) $row['vnum']);
        }

        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $partial
     * @param array<string, mixed> $header
     */
    protected function storeHubView(string $tab, array $partial, array $header = [], int $status = 200): Response
    {
        return $this->adminView('store', 'pages/store-hub.twig', array_merge([
            'title' => $this->t('admin.store.hub_title'),
            'pageLead' => $this->t('admin.store.hub_lead'),
            'activeTab' => $tab,
            'storeBaseUrl' => AdminPaths::store(),
            'initialPartial' => $partial,
        ], $header), $status);
    }

    /**
     * @param array<string, mixed>|null $selected
     * @param array<string, mixed>|null $form
     * @return array<string, mixed>
     */
    protected function categoryWorkspaceData(
        ?array $selected,
        ?array $form = null,
        bool $isEdit = false,
        ?string $error = null,
        ?string $categoryTab = null,
    ): array {
        $isCreating = $form !== null && !$isEdit;
        $showForm = $selected !== null || $isCreating;

        if ($form === null && $selected !== null) {
            $form = $selected;
            $isEdit = true;
            $showForm = true;
        }

        if ($categoryTab === null) {
            $categoryTab = $isEdit ? $this->categoryPanel() : 'dados';
        }

        $data = [
            'categoryTree' => $this->categories->treeForAdmin(),
            'parentOptions' => $this->categories->listAllForSelect(),
            'selectedCategory' => $selected,
            'category' => $form,
            'isEdit' => $isEdit,
            'showForm' => $showForm,
            'error' => $error,
            'moveUrl' => AdminPaths::storeCategories() . '/move',
            'categoryTab' => $categoryTab,
            'categoryProducts' => [],
            'itemSearchUrl' => null,
            'addProductsUrl' => null,
        ];

        if ($isEdit && $selected !== null) {
            $categoryId = (int) $selected['id'];
            $data['itemSearchUrl'] = AdminPaths::storeCategory($categoryId) . '/item-search';
            $data['addProductsUrl'] = AdminPaths::storeCategory($categoryId) . '/products';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $workspace
     * @return array<string, mixed>
     */
    protected function categoriesHubHeader(array $workspace): array
    {
        $showForm = (bool) ($workspace['showForm'] ?? false);
        $isEdit = (bool) ($workspace['isEdit'] ?? false);
        $categoryTab = (string) ($workspace['categoryTab'] ?? 'dados');

        if ($showForm && $isEdit && $categoryTab === 'products') {
            return [
                'formId' => 'admin-category-products-form',
                'saveLabel' => $this->t('admin.save'),
            ];
        }

        if ($showForm && (!$isEdit || $categoryTab === 'dados')) {
            return [
                'formId' => 'admin-item-shop-category-form',
                'saveLabel' => $this->t('admin.save'),
            ];
        }

        if (!$showForm) {
            return [
                'headerHref' => AdminPaths::storeCategoryNew(),
                'headerActionLabel' => $this->t('admin.item_shop.categories.add_root'),
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function categoryPrefill(?int $parentId = null): array
    {
        return [
            'parent_id' => $parentId,
            'name' => '',
            'slug' => '',
            'sort_order' => 0,
            'enabled' => 1,
        ];
    }

    protected function categoryPanel(): string
    {
        $panel = (string) ($_GET['panel'] ?? '');

        if (in_array($panel, ['dados', 'products'], true)) {
            return $panel;
        }

        $tab = (string) ($_GET['tab'] ?? '');

        if (in_array($tab, ['dados', 'products'], true)) {
            return $tab;
        }

        return 'products';
    }
}
