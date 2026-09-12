<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
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
