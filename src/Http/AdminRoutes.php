<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

use FastRoute\RouteCollector;
use Mt2Cms\Http\Controller\AdminAccountSecurityController;
use Mt2Cms\Http\Controller\AdminAccountsController;
use Mt2Cms\Http\Controller\AdminAdminsController;
use Mt2Cms\Http\Controller\AdminAuditLogController;
use Mt2Cms\Http\Controller\AdminAuthController;
use Mt2Cms\Http\Controller\CaptchaController;
use Mt2Cms\Http\Controller\AdminAwardsController;
use Mt2Cms\Http\Controller\AdminBansController;
use Mt2Cms\Http\Controller\AdminCashPackagesController;
use Mt2Cms\Http\Controller\AdminCommunityController;
use Mt2Cms\Http\Controller\AdminDownloadsController;
use Mt2Cms\Http\Controller\AdminEventsController;
use Mt2Cms\Http\Controller\AdminPaymentsController;
use Mt2Cms\Http\Controller\AdminCharactersController;
use Mt2Cms\Http\Controller\AdminDashboardController;
use Mt2Cms\Http\Controller\AdminDropsController;
use Mt2Cms\Http\Controller\AdminGameProtoController;
use Mt2Cms\Http\Controller\AdminGmsController;
use Mt2Cms\Http\Controller\AdminGuildsController;
use Mt2Cms\Http\Controller\AdminItemShopCategoriesController;
use Mt2Cms\Http\Controller\AdminItemShopOrdersController;
use Mt2Cms\Http\Controller\AdminItemShopProductsController;
use Mt2Cms\Http\Controller\AdminLogsController;
use Mt2Cms\Http\Controller\AdminNewsCommentsController;
use Mt2Cms\Http\Controller\AdminNewsHubController;
use Mt2Cms\Http\Controller\AdminRolesController;
use Mt2Cms\Http\Controller\AdminNewsPostsController;
use Mt2Cms\Http\Controller\AdminNewsSettingsController;
use Mt2Cms\Http\Controller\AdminReferralsController;
use Mt2Cms\Http\Controller\AdminRefineController;
use Mt2Cms\Http\Controller\AdminUnstuckController;
use Mt2Cms\Http\Controller\AdminSettingsController;
use Mt2Cms\Http\Controller\AdminShopsController;
use Mt2Cms\Http\Controller\AdminStoreHubController;
use Mt2Cms\Http\Controller\AdminTicketsController;

final class AdminRoutes
{
    public static function register(RouteCollector $r): void
    {
        $r->addRoute('GET', '/admin/captcha.svg', [CaptchaController::class, 'adminSvg']);
        $r->addRoute('GET', '/admin/login', [AdminAuthController::class, 'showLogin']);
        $r->addRoute('POST', '/admin/login', [AdminAuthController::class, 'login']);
        $r->addRoute('GET', '/admin/login/2fa', [AdminAuthController::class, 'showTwoFactor']);
        $r->addRoute('POST', '/admin/login/2fa', [AdminAuthController::class, 'verifyTwoFactor']);
        $r->addRoute('POST', '/admin/logout', [AdminAuthController::class, 'logout']);
        $r->addRoute('GET', '/admin/account/security', [AdminAccountSecurityController::class, 'show']);
        $r->addRoute('POST', '/admin/account/security/enroll', [AdminAccountSecurityController::class, 'startEnroll']);
        $r->addRoute('POST', '/admin/account/security/confirm', [AdminAccountSecurityController::class, 'confirmEnroll']);
        $r->addRoute('GET', '/admin', [AdminDashboardController::class, 'index']);

        // Settings
        $r->addRoute('GET', '/admin/settings/registration', [AdminSettingsController::class, 'registration']);
        $r->addRoute('POST', '/admin/settings/registration', [AdminSettingsController::class, 'saveRegistration']);
        $r->addRoute('GET', '/admin/settings/themes', [AdminSettingsController::class, 'themes']);
        $r->addRoute('POST', '/admin/settings/themes', [AdminSettingsController::class, 'saveThemes']);
        $r->addRoute('GET', '/admin/settings/locale', [AdminSettingsController::class, 'locale']);
        $r->addRoute('POST', '/admin/settings/locale', [AdminSettingsController::class, 'saveLocale']);
        $r->addRoute('GET', '/admin/settings/security', [AdminSettingsController::class, 'security']);
        $r->addRoute('POST', '/admin/settings/security', [AdminSettingsController::class, 'saveSecurity']);
        $r->addRoute('GET', '/admin/settings/community', [AdminCommunityController::class, 'channels']);
        $r->addRoute('POST', '/admin/settings/community', [AdminCommunityController::class, 'saveChannels']);
        $r->addRoute('POST', '/admin/settings/community/channels/{id:\d+}/delete', [AdminCommunityController::class, 'deleteChannel']);
        $r->addRoute('GET', '/admin/settings/unstuck', [AdminUnstuckController::class, 'settings']);
        $r->addRoute('POST', '/admin/settings/unstuck', [AdminUnstuckController::class, 'saveSettings']);

        // System
        $r->addRoute('GET', '/admin/system/admins', [AdminAdminsController::class, 'index']);
        $r->addRoute('GET', '/admin/system/admins/new', [AdminAdminsController::class, 'create']);
        $r->addRoute('POST', '/admin/system/admins', [AdminAdminsController::class, 'store']);
        $r->addRoute('POST', '/admin/system/admins/mass', [AdminAdminsController::class, 'mass']);
        $r->addRoute('GET', '/admin/system/admins/{id:\d+}', [AdminAdminsController::class, 'edit']);
        $r->addRoute('POST', '/admin/system/admins/{id:\d+}', [AdminAdminsController::class, 'update']);
        $r->addRoute('POST', '/admin/system/admins/{id:\d+}/reset-2fa', [AdminAdminsController::class, 'resetTwoFactor']);
        $r->addRoute('POST', '/admin/system/admins/{id:\d+}/delete', [AdminAdminsController::class, 'destroy']);
        $r->addRoute('GET', '/admin/system/roles', [AdminRolesController::class, 'index']);
        $r->addRoute('POST', '/admin/system/roles/mass', [AdminRolesController::class, 'mass']);
        $r->addRoute('GET', '/admin/system/roles/new', [AdminRolesController::class, 'create']);
        $r->addRoute('POST', '/admin/system/roles', [AdminRolesController::class, 'store']);
        $r->addRoute('GET', '/admin/system/roles/{slug:[a-z0-9-]+}', [AdminRolesController::class, 'edit']);
        $r->addRoute('POST', '/admin/system/roles/{slug:[a-z0-9-]+}', [AdminRolesController::class, 'update']);
        $r->addRoute('POST', '/admin/system/roles/{slug:[a-z0-9-]+}/reassign', [AdminRolesController::class, 'reassign']);
        $r->addRoute('POST', '/admin/system/roles/{slug:[a-z0-9-]+}/delete', [AdminRolesController::class, 'destroy']);
        $r->addRoute('GET', '/admin/system/audit-log', [AdminAuditLogController::class, 'index']);

        // Game
        $r->addRoute('GET', '/admin/game/accounts', [AdminAccountsController::class, 'index']);
        $r->addRoute('GET', '/admin/game/accounts/new', [AdminAccountsController::class, 'create']);
        $r->addRoute('POST', '/admin/game/accounts', [AdminAccountsController::class, 'store']);
        $r->addRoute('GET', '/admin/game/accounts/{id:\d+}', [AdminAccountsController::class, 'edit']);
        $r->addRoute('POST', '/admin/game/accounts/{id:\d+}', [AdminAccountsController::class, 'update']);
        $r->addRoute('POST', '/admin/game/accounts/{id:\d+}/block', [AdminAccountsController::class, 'block']);
        $r->addRoute('POST', '/admin/game/accounts/{id:\d+}/unblock', [AdminAccountsController::class, 'unblock']);
        $r->addRoute('POST', '/admin/game/accounts/{id:\d+}/delete', [AdminAccountsController::class, 'destroy']);
        $r->addRoute('POST', '/admin/game/accounts/mass', [AdminAccountsController::class, 'mass']);
        $r->addRoute('GET', '/admin/game/characters', [AdminCharactersController::class, 'index']);
        $r->addRoute('GET', '/admin/game/characters/{id:\d+}', [AdminCharactersController::class, 'show']);
        $r->addRoute('POST', '/admin/game/characters/{id:\d+}/unstuck', [AdminCharactersController::class, 'unstuck']);
        $r->addRoute('GET', '/admin/game/owned-items/{id:\d+}', [AdminCharactersController::class, 'showOwnedItem']);
        $r->addRoute('GET', '/admin/game/referrals', [AdminReferralsController::class, 'index']);
        $r->addRoute('POST', '/admin/game/referrals/settings', [AdminReferralsController::class, 'saveSettings']);
        $r->addRoute('GET', '/admin/game/guilds', [AdminGuildsController::class, 'index']);
        $r->addRoute('GET', '/admin/game/guilds/{id:\d+}', [AdminGuildsController::class, 'show']);
        $r->addRoute('POST', '/admin/game/guilds/{id:\d+}', [AdminGuildsController::class, 'update']);
        $r->addRoute('POST', '/admin/game/guilds/{id:\d+}/kick', [AdminGuildsController::class, 'kick']);
        $r->addRoute('POST', '/admin/game/guilds/{id:\d+}/comment/{commentId:\d+}/delete', [AdminGuildsController::class, 'deleteComment']);
        $r->addRoute('POST', '/admin/game/guilds/{id:\d+}/dissolve', [AdminGuildsController::class, 'dissolve']);
        $r->addRoute('GET', '/admin/game/awards', [AdminAwardsController::class, 'index']);
        $r->addRoute('GET', '/admin/game/awards/new', [AdminAwardsController::class, 'create']);
        $r->addRoute('POST', '/admin/game/awards', [AdminAwardsController::class, 'store']);
        $r->addRoute('POST', '/admin/game/awards/{id:\d+}/delete', [AdminAwardsController::class, 'destroy']);
        $r->addRoute('POST', '/admin/game/awards/mass', [AdminAwardsController::class, 'mass']);
        $r->addRoute('GET', '/admin/game/bans', [AdminBansController::class, 'index']);
        $r->addRoute('GET', '/admin/game/bans/new', [AdminBansController::class, 'create']);
        $r->addRoute('POST', '/admin/game/bans', [AdminBansController::class, 'store']);
        $r->addRoute('POST', '/admin/game/bans/mass', [AdminBansController::class, 'mass']);

        // Content — news hub
        $r->addRoute('GET', '/admin/content/news', [AdminNewsHubController::class, 'index']);
        $r->addRoute('GET', '/admin/content/news/posts/new', [AdminNewsPostsController::class, 'create']);
        $r->addRoute('POST', '/admin/content/news/posts', [AdminNewsPostsController::class, 'store']);
        $r->addRoute('POST', '/admin/content/news/posts/upload', [AdminNewsPostsController::class, 'upload']);
        $r->addRoute('POST', '/admin/content/news/posts/mass', [AdminNewsPostsController::class, 'mass']);
        $r->addRoute('GET', '/admin/content/news/posts/{id:\d+}', [AdminNewsPostsController::class, 'edit']);
        $r->addRoute('POST', '/admin/content/news/posts/{id:\d+}', [AdminNewsPostsController::class, 'update']);
        $r->addRoute('POST', '/admin/content/news/posts/{id:\d+}/delete', [AdminNewsPostsController::class, 'destroy']);
        $r->addRoute('POST', '/admin/content/news/comments/{id:\d+}/approve', [AdminNewsCommentsController::class, 'approveComment']);
        $r->addRoute('POST', '/admin/content/news/comments/{id:\d+}/reject', [AdminNewsCommentsController::class, 'rejectComment']);
        $r->addRoute('POST', '/admin/content/news/comments/{id:\d+}/delete', [AdminNewsCommentsController::class, 'deleteComment']);
        $r->addRoute('POST', '/admin/content/news/comments/mass', [AdminNewsCommentsController::class, 'massComments']);
        $r->addRoute('POST', '/admin/content/news/settings', [AdminNewsSettingsController::class, 'saveSettings']);

        // Content — tickets
        $r->addRoute('GET', '/admin/content/tickets', [AdminTicketsController::class, 'index']);
        $r->addRoute('POST', '/admin/content/tickets/mass', [AdminTicketsController::class, 'mass']);
        $r->addRoute('GET', '/admin/content/tickets/{id:\d+}', [AdminTicketsController::class, 'show']);
        $r->addRoute('GET', '/admin/content/tickets/{id:\d+}/attachments/{attachmentId:\d+}', [AdminTicketsController::class, 'downloadAttachment']);
        $r->addRoute('POST', '/admin/content/tickets/{id:\d+}/reply', [AdminTicketsController::class, 'reply']);
        $r->addRoute('POST', '/admin/content/tickets/{id:\d+}/close', [AdminTicketsController::class, 'close']);
        $r->addRoute('POST', '/admin/content/tickets/{id:\d+}/reopen', [AdminTicketsController::class, 'reopen']);
        $r->addRoute('GET', '/admin/content/downloads', [AdminDownloadsController::class, 'index']);
        $r->addRoute('GET', '/admin/content/downloads/new', [AdminDownloadsController::class, 'create']);
        $r->addRoute('POST', '/admin/content/downloads', [AdminDownloadsController::class, 'store']);
        $r->addRoute('GET', '/admin/content/downloads/{id:\d+}', [AdminDownloadsController::class, 'edit']);
        $r->addRoute('POST', '/admin/content/downloads/{id:\d+}', [AdminDownloadsController::class, 'update']);
        $r->addRoute('POST', '/admin/content/downloads/mass', [AdminDownloadsController::class, 'mass']);
        $r->addRoute('GET', '/admin/content/events', [AdminEventsController::class, 'index']);
        $r->addRoute('GET', '/admin/content/events/new', [AdminEventsController::class, 'create']);
        $r->addRoute('POST', '/admin/content/events', [AdminEventsController::class, 'store']);
        $r->addRoute('POST', '/admin/content/events/mass', [AdminEventsController::class, 'mass']);
        $r->addRoute('GET', '/admin/content/events/{id:\d+}', [AdminEventsController::class, 'edit']);
        $r->addRoute('POST', '/admin/content/events/{id:\d+}', [AdminEventsController::class, 'update']);

        // Store hub
        $r->addRoute('GET', '/admin/store', [AdminStoreHubController::class, 'index']);
        $r->addRoute('GET', '/admin/store/products', [AdminItemShopProductsController::class, 'productsIndex']);
        $r->addRoute('GET', '/admin/store/products/new', [AdminItemShopProductsController::class, 'productsCreate']);
        $r->addRoute('POST', '/admin/store/products', [AdminItemShopProductsController::class, 'productsStore']);
        $r->addRoute('GET', '/admin/store/products/{id:\d+}', [AdminItemShopProductsController::class, 'productsEdit']);
        $r->addRoute('POST', '/admin/store/products/{id:\d+}', [AdminItemShopProductsController::class, 'productsUpdate']);
        $r->addRoute('POST', '/admin/store/products/{id:\d+}/delete', [AdminItemShopProductsController::class, 'productsDestroy']);
        $r->addRoute('POST', '/admin/store/products/mass', [AdminItemShopProductsController::class, 'mass']);
        $r->addRoute('GET', '/admin/store/categories', [AdminItemShopCategoriesController::class, 'categoriesIndex']);
        $r->addRoute('GET', '/admin/store/categories/new', [AdminItemShopCategoriesController::class, 'categoriesCreate']);
        $r->addRoute('POST', '/admin/store/categories', [AdminItemShopCategoriesController::class, 'categoriesStore']);
        $r->addRoute('POST', '/admin/store/categories/move', [AdminItemShopCategoriesController::class, 'categoriesMove']);
        $r->addRoute('GET', '/admin/store/categories/{id:\d+}/item-search', [AdminItemShopCategoriesController::class, 'categoriesItemSearch']);
        $r->addRoute('POST', '/admin/store/categories/{id:\d+}/products', [AdminItemShopCategoriesController::class, 'categoriesAddProducts']);
        $r->addRoute('POST', '/admin/store/categories/{id:\d+}/products/{productId:\d+}', [AdminItemShopCategoriesController::class, 'categoriesUpdateProduct']);
        $r->addRoute('POST', '/admin/store/categories/{id:\d+}/products/{productId:\d+}/delete', [AdminItemShopCategoriesController::class, 'categoriesRemoveProduct']);
        $r->addRoute('GET', '/admin/store/categories/{id:\d+}', [AdminItemShopCategoriesController::class, 'categoriesEdit']);
        $r->addRoute('POST', '/admin/store/categories/{id:\d+}', [AdminItemShopCategoriesController::class, 'categoriesUpdate']);
        $r->addRoute('POST', '/admin/store/categories/{id:\d+}/delete', [AdminItemShopCategoriesController::class, 'categoriesDestroy']);
        $r->addRoute('GET', '/admin/store/orders', [AdminItemShopOrdersController::class, 'ordersIndex']);
        $r->addRoute('GET', '/admin/store/packages', [AdminCashPackagesController::class, 'index']);
        $r->addRoute('GET', '/admin/store/packages/new', [AdminCashPackagesController::class, 'create']);
        $r->addRoute('POST', '/admin/store/packages', [AdminCashPackagesController::class, 'store']);
        $r->addRoute('GET', '/admin/store/packages/{id:\d+}', [AdminCashPackagesController::class, 'edit']);
        $r->addRoute('POST', '/admin/store/packages/{id:\d+}', [AdminCashPackagesController::class, 'update']);
        $r->addRoute('POST', '/admin/store/packages/mass', [AdminCashPackagesController::class, 'mass']);
        $r->addRoute('GET', '/admin/store/payments', [AdminPaymentsController::class, 'index']);
        $r->addRoute('GET', '/admin/store/payments/{id:\d+}', [AdminPaymentsController::class, 'show']);
        $r->addRoute('POST', '/admin/store/payments/{id:\d+}/recredit', [AdminPaymentsController::class, 'recredit']);

        // Game data
        $r->addRoute('GET', '/admin/game-data/shops', [AdminShopsController::class, 'index']);
        $r->addRoute('GET', '/admin/game-data/shops/new', [AdminShopsController::class, 'create']);
        $r->addRoute('POST', '/admin/game-data/shops', [AdminShopsController::class, 'store']);
        $r->addRoute('GET', '/admin/game-data/shops/{id:\d+}', [AdminShopsController::class, 'edit']);
        $r->addRoute('POST', '/admin/game-data/shops/{id:\d+}', [AdminShopsController::class, 'update']);
        $r->addRoute('POST', '/admin/game-data/shops/{id:\d+}/delete', [AdminShopsController::class, 'destroy']);
        $r->addRoute('POST', '/admin/game-data/shops/{id:\d+}/items', [AdminShopsController::class, 'addItem']);
        $r->addRoute('POST', '/admin/game-data/shops/{id:\d+}/items/delete', [AdminShopsController::class, 'removeItem']);
        $r->addRoute('POST', '/admin/game-data/shops/mass', [AdminShopsController::class, 'mass']);
        $r->addRoute('GET', '/admin/game-data/refine', [AdminRefineController::class, 'index']);
        $r->addRoute('GET', '/admin/game-data/refine/new', [AdminRefineController::class, 'create']);
        $r->addRoute('POST', '/admin/game-data/refine', [AdminRefineController::class, 'store']);
        $r->addRoute('GET', '/admin/game-data/refine/{id:\d+}', [AdminRefineController::class, 'edit']);
        $r->addRoute('POST', '/admin/game-data/refine/{id:\d+}', [AdminRefineController::class, 'update']);
        $r->addRoute('POST', '/admin/game-data/refine/{id:\d+}/delete', [AdminRefineController::class, 'destroy']);
        $r->addRoute('POST', '/admin/game-data/refine/mass', [AdminRefineController::class, 'mass']);
        $r->addRoute('GET', '/admin/game-data/drops', [AdminDropsController::class, 'index']);
        $r->addRoute('GET', '/admin/game-data/drops/etc', [AdminDropsController::class, 'etc']);
        $r->addRoute('POST', '/admin/game-data/drops/etc', [AdminDropsController::class, 'saveEtc']);
        $r->addRoute('GET', '/admin/game-data/drops/common', [AdminDropsController::class, 'common']);
        $r->addRoute('POST', '/admin/game-data/drops/common', [AdminDropsController::class, 'saveCommon']);
        $r->addRoute('GET', '/admin/game-data/drops/mob/{id:\d+}', [AdminDropsController::class, 'mob']);
        $r->addRoute('POST', '/admin/game-data/drops/mob/{id:\d+}', [AdminDropsController::class, 'saveMob']);
        $r->addRoute('GET', '/admin/game-data/gms', [AdminGmsController::class, 'index']);
        $r->addRoute('GET', '/admin/game-data/gms/new', [AdminGmsController::class, 'create']);
        $r->addRoute('POST', '/admin/game-data/gms', [AdminGmsController::class, 'store']);
        $r->addRoute('GET', '/admin/game-data/gms/{id:\d+}', [AdminGmsController::class, 'edit']);
        $r->addRoute('POST', '/admin/game-data/gms/{id:\d+}', [AdminGmsController::class, 'update']);
        $r->addRoute('POST', '/admin/game-data/gms/{id:\d+}/delete', [AdminGmsController::class, 'destroy']);
        $r->addRoute('POST', '/admin/game-data/gms/hosts', [AdminGmsController::class, 'addHost']);
        $r->addRoute('POST', '/admin/game-data/gms/hosts/delete', [AdminGmsController::class, 'deleteHost']);
        $r->addRoute('POST', '/admin/game-data/gms/mass', [AdminGmsController::class, 'mass']);
        $r->addRoute('GET', '/admin/game-data/{kind:items|mobs}', [AdminGameProtoController::class, 'index']);
        $r->addRoute('POST', '/admin/game-data/{kind:items|mobs}/mass', [AdminGameProtoController::class, 'mass']);
        $r->addRoute('GET', '/admin/game-data/{kind:items|mobs}/new', [AdminGameProtoController::class, 'create']);
        $r->addRoute('POST', '/admin/game-data/{kind:items|mobs}', [AdminGameProtoController::class, 'store']);
        $r->addRoute('GET', '/admin/game-data/{kind:items|mobs}/{id:\d+}', [AdminGameProtoController::class, 'edit']);
        $r->addRoute('POST', '/admin/game-data/{kind:items|mobs}/{id:\d+}', [AdminGameProtoController::class, 'update']);
        $r->addRoute('POST', '/admin/game-data/{kind:items|mobs}/{id:\d+}/delete', [AdminGameProtoController::class, 'destroy']);

        // Logs hub
        $r->addRoute('GET', '/admin/logs', [AdminLogsController::class, 'index']);
    }
}
