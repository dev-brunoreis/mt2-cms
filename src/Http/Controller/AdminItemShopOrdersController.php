<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\Grid\GridRunner;
use Mt2Cms\Http\Response;

class AdminItemShopOrdersController extends AdminItemShopBaseController
{
    public function ordersIndex(): Response
    {
        $spec = $this->orders->gridDefinition()->spec();
        $query = $this->gridQuery($spec);
        $grid = GridRunner::fetch(
            $spec,
            $query,
            fn ($q) => $this->orders->countForGrid($q),
            fn ($q) => $this->enrichOrders($this->orders->listForGrid($q)),
        );

        return $this->adminView('store', 'pages/item-shop-orders.twig', [
            'title' => $this->t('admin.item_shop.orders.title'),
            'pageLead' => $this->t('admin.item_shop.orders.lead'),
            'grid' => $grid,
        ]);
    }
}
