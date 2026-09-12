<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller\Admin;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Http\Response;

class AdminItemShopOrdersController extends AdminItemShopBaseController
{
    public function ordersIndex(): Response
    {
        return $this->redirect(AdminPaths::store('orders'));
    }
}
