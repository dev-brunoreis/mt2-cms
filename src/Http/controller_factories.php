<?php

declare(strict_types=1);


use Mt2Cms\Setup\EnvWriter;

/**
 * @return array<class-string, callable(\Mt2Cms\Application): object>
 */
return [
    \Mt2Cms\Http\Controller\HealthController::class => static fn ($app) => new \Mt2Cms\Http\Controller\HealthController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->cmsDb, $app->db,
    ),
    \Mt2Cms\Http\Controller\HomeController::class => static fn ($app) => new \Mt2Cms\Http\Controller\HomeController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->news, $app->events, $app->players, $app->settings,
    ),
    \Mt2Cms\Http\Controller\EventsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\EventsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->events,
    ),
    \Mt2Cms\Http\Controller\NewsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\NewsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->news, $app->newsComments, $app->settings,
    ),
    \Mt2Cms\Http\Controller\TicketController::class => static fn ($app) => new \Mt2Cms\Http\Controller\TicketController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->tickets, $app->ticketUploads, $app->htmlSanitizer, $app->discord,
    ),
    \Mt2Cms\Http\Controller\AuthController::class => static fn ($app) => new \Mt2Cms\Http\Controller\AuthController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->accounts, $app->settings, $app->accountEmailService, $app->banService, $app->mailer, $app->referralService,
    ),
    \Mt2Cms\Http\Controller\AccountController::class => static fn ($app) => new \Mt2Cms\Http\Controller\AccountController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->players, $app->accounts, $app->accountEmailService, $app->itemShopOrders, $app->payments, $app->notifications, $app->gameProto, $app->mailer, $app->settings, $app->unstuckService, $app->referralService,
    ),
    \Mt2Cms\Http\Controller\PasswordController::class => static fn ($app) => new \Mt2Cms\Http\Controller\PasswordController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->accountEmailService, $app->mailer, $app->settings,
    ),
    \Mt2Cms\Http\Controller\EmailVerificationController::class => static fn ($app) => new \Mt2Cms\Http\Controller\EmailVerificationController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->accountEmailService, $app->mailer,
    ),
    \Mt2Cms\Http\Controller\StatusController::class => static fn ($app) => new \Mt2Cms\Http\Controller\StatusController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->players, $app->serverChannels, $app->settings,
    ),
    \Mt2Cms\Http\Controller\DownloadsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\DownloadsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->downloads, $app->downloadUploads,
    ),
    \Mt2Cms\Http\Controller\DonateController::class => static fn ($app) => new \Mt2Cms\Http\Controller\DonateController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->cashPackages, $app->payments, $app->paymentCheckout, $app->cashCredits, $app->paypal, $app->accountEmailService, $app->settings,
    ),
    \Mt2Cms\Http\Controller\PaymentWebhookController::class => static fn ($app) => new \Mt2Cms\Http\Controller\PaymentWebhookController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->paypal, $app->cashCredits,
    ),
    \Mt2Cms\Http\Controller\ItemShopController::class => static fn ($app) => new \Mt2Cms\Http\Controller\ItemShopController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopPurchases, $app->itemTooltips, $app->settings, $app->accountEmailService,
    ),
    \Mt2Cms\Http\Controller\RankingController::class => static fn ($app) => new \Mt2Cms\Http\Controller\RankingController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->players, $app->guilds,
    ),
    \Mt2Cms\Http\Controller\PlayerController::class => static fn ($app) => new \Mt2Cms\Http\Controller\PlayerController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->players, $app->guilds, $app->unstuckService, $app->settings,
    ),
    \Mt2Cms\Http\Controller\GameIconController::class => static fn ($app) => new \Mt2Cms\Http\Controller\GameIconController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->icons,
    ),
    \Mt2Cms\Http\Controller\LocaleController::class => static fn ($app) => new \Mt2Cms\Http\Controller\LocaleController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->locales,
    ),
    \Mt2Cms\Http\Controller\CaptchaController::class => static fn ($app) => new \Mt2Cms\Http\Controller\CaptchaController(
        $app->theme, $app->auth, $app->csrf, $app->translator,
    ),
    \Mt2Cms\Http\Controller\SetupController::class => static fn ($app) => new \Mt2Cms\Http\Controller\SetupController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->themeCatalog, new EnvWriter(), $app->needsAdminRecovery,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAuthController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAuthController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->settings, $app->adminTotp,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAccountSecurityController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAccountSecurityController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->adminTotp, $app->settings,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminDashboardController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminDashboardController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->players,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminSettingsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminSettingsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->settings, $app->themeCatalog, $app->locales, $app->serverChannels, $app->unstuckService,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAccountsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAccountsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->accounts, $app->players, $app->logs, $app->notificationService,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminCharactersController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminCharactersController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->players, $app->items, $app->guilds, $app->logs, $app->accounts, $app->settings, $app->unstuckService,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminUnstuckController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminUnstuckController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->settings, $app->unstuckService,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminReferralsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminReferralsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->referralRepo, $app->settings,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminGameProtoController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminGameProtoController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->gameProto, $app->protoFields, $app->mobDrops, $app->protoEnums, $app->shops,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminLogsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminLogsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->logs,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminGuildsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminGuildsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->guilds,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminGmsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminGmsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->gms, $app->accounts,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAwardsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAwardsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->awards, $app->accounts, $app->players, $app->gameProto, $app->notificationService,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminNewsHubController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminNewsHubController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->news, $app->newsComments, $app->settings, $app->htmlSanitizer, $app->newsUploads,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminStoreHubController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminStoreHubController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopOrders, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminNewsPostsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminNewsPostsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->news, $app->newsComments, $app->settings, $app->htmlSanitizer, $app->newsUploads, $app->discord,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminNewsCommentsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminNewsCommentsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->news, $app->newsComments, $app->settings, $app->htmlSanitizer, $app->newsUploads,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminNewsSettingsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminNewsSettingsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->news, $app->newsComments, $app->settings, $app->htmlSanitizer, $app->newsUploads,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminTicketsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminTicketsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->tickets, $app->ticketUploads, $app->htmlSanitizer,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminItemShopProductsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminItemShopProductsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopOrders, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminItemShopCategoriesController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminItemShopCategoriesController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopOrders, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminItemShopCategoryProductsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminItemShopCategoryProductsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopOrders, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminItemShopOrdersController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminItemShopOrdersController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->itemShopCategories, $app->itemShopProducts, $app->itemShopOrders, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminShopsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminShopsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->shops, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminRefineController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminRefineController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->refine, $app->gameProto,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminDropsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminDropsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->dropFiles, $app->mobDrops, $app->gameProto, $app->gameProfile,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAdminsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAdminsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, new \Mt2Cms\Repository\AdminRepository($app->cmsDb, $app->adminRoles), $app->adminRoles, $app->adminTotp,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminRolesController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminRolesController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->adminRoles,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminAuditLogController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminAuditLogController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, new \Mt2Cms\Repository\AdminAuditRepository($app->cmsDb),
    ),
    \Mt2Cms\Http\Controller\Admin\AdminBansController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminBansController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->banRepo, $app->banService, $app->accounts,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminDownloadsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminDownloadsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->downloads, $app->downloadUploads,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminBannersHubController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminBannersHubController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->banners, $app->bannerUploads,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminEventsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminEventsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->events, $app->eventService, $app->htmlSanitizer,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminCommunityController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminCommunityController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->serverChannels, $app->settings,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminPaymentMethodsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminPaymentMethodsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->settings,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminCashPackagesController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminCashPackagesController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->cashPackages, $app->settings,
    ),
    \Mt2Cms\Http\Controller\Admin\AdminPaymentsController::class => static fn ($app) => new \Mt2Cms\Http\Controller\Admin\AdminPaymentsController(
        $app->theme, $app->auth, $app->csrf, $app->translator, $app->adminAuth, $app->adminTheme, $app->acl, $app->adminAudit, $app->payments, $app->cashCredits, $app->paymentExpiry,
    ),
];
