<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminStoreHubController extends AdminItemShopBaseController
{
    private const TABS = ['categories', 'orders'];

    public function index(): Response
    {
        if ($redirect = $this->requireAdminSection('store')) {
            return $redirect;
        }

        $tab = $this->requestedTab(self::TABS, 'categories');

        if ($this->wantsTabPartial()) {
            return $this->renderTabPartial($tab);
        }

        if ($tab === 'categories') {
            $missing = $this->missingCategoryFromRequest();

            if ($missing !== null) {
                $this->flash('error', $this->t('admin.item_shop.categories.not_found'));

                return $this->redirect(AdminPaths::store());
            }
        }

        $payload = $this->partialPayload($tab);
        $header = $tab === 'categories'
            ? $this->categoriesHubHeader($payload['data'])
            : [];

        return $this->storeHubView($tab, $payload, $header);
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
        if ($tab === 'orders') {
            return [
                'template' => 'pages/store-orders-partial.twig',
                'data' => ['grid' => $this->ordersGrid()],
            ];
        }

        return [
            'template' => 'pages/store-categories-partial.twig',
            'data' => $this->categoriesWorkspaceFromRequest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoriesWorkspaceFromRequest(): array
    {
        if (isset($_GET['new'])) {
            $parentId = (int) ($_GET['parent_id'] ?? 0);

            return $this->categoryWorkspaceData(
                null,
                $this->categoryPrefill($parentId > 0 ? $parentId : null),
                false,
            );
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id > 0) {
            $category = $this->categories->findById($id);

            if ($category !== null) {
                return $this->categoryWorkspaceData($category, $category, true);
            }
        }

        return $this->categoryWorkspaceData(null);
    }

    private function missingCategoryFromRequest(): ?int
    {
        if (isset($_GET['new'])) {
            return null;
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id < 1) {
            return null;
        }

        return $this->categories->findById($id) === null ? $id : null;
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
            fn ($q) => $this->enrichOrders($this->orders->listForGrid($q)),
        );
    }
}
