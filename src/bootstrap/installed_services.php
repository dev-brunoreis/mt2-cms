<?php

declare(strict_types=1);

use Mt2Cms\Admin\AdminRuntime;
use Mt2Cms\Application;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Game\Display;
use Mt2Cms\Game\Drop\GroupTextParser;
use Mt2Cms\Game\Drop\GroupTextWriter;
use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\ItemDescCatalog;
use Mt2Cms\Game\ItemIconCatalog;
use Mt2Cms\Game\ItemStats;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Game\Proto\ProtoIndexCache;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Mail\SymfonyMailer;
use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PayPalGateway;
use Mt2Cms\Repository\AccountEmailRepository;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\AclRepository;
use Mt2Cms\Repository\AdminAuditRepository;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\AdminRoleRepository;
use Mt2Cms\Repository\AdminTotpRepository;
use Mt2Cms\Repository\BanRepository;
use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\DownloadRepository;
use Mt2Cms\Repository\BannerRepository;
use Mt2Cms\Repository\EmailTokenRepository;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Repository\GmRepository;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\ItemAwardRepository;
use Mt2Cms\Repository\ItemRepository;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Repository\NewsCommentRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Repository\NotificationRepository;
use Mt2Cms\Repository\PaymentEventRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Repository\ProtoNameRepository;
use Mt2Cms\Repository\ReferralRepository;
use Mt2Cms\Repository\RefineRepository;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Repository\TicketRepository;
use Mt2Cms\Repository\UnstuckRepository;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\BanService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\DiscordWebhookService;
use Mt2Cms\Service\DownloadUploadService;
use Mt2Cms\Service\BannerUploadService;
use Mt2Cms\Service\LogoUploadService;
use Mt2Cms\Service\SeoImageUploadService;
use Mt2Cms\Service\SeoService;
use Mt2Cms\Service\BannerSeedService;
use Mt2Cms\Service\ImageVariantService;
use Mt2Cms\Service\DropFileService;
use Mt2Cms\Service\EventService;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\ItemShopPurchaseService;
use Mt2Cms\Service\ItemTooltipBuilder;
use Mt2Cms\Service\MobDropService;
use Mt2Cms\Service\NewsUploadService;
use Mt2Cms\Service\NotificationService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Service\PaymentExpiryService;
use Mt2Cms\Service\PaymentStatsService;
use Mt2Cms\Service\PlayerCensusService;
use Mt2Cms\Service\PaymentWebhookProcessor;
use Mt2Cms\Service\ReferralService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Service\TicketUploadService;
use Mt2Cms\Service\UnstuckService;
use Mt2Cms\Support\Database;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Support\Log;
use Mt2Cms\I18n\Translator;

/**
 * Wire installed-app services onto Application public props.
 * Call assertAppKey / cmsDb / assertSchemaCurrent before this.
 *
 * @return callable(Application): void
 */
return static function (Application $app): void {
    $adminRepo = new AdminRepository($app->cmsDb);
    $app->needsAdminRecovery = $adminRepo->count() === 0;
    $app->adminAuth = new AdminAuth($adminRepo);
    $app->adminRoles = new AdminRoleRepository($app->cmsDb);
    $app->adminTotp = new AdminTotpRepository($app->cmsDb);
    $app->acl = new AclService(new AclRepository($app->cmsDb), $app->adminRoles);

    $app->settingsRepo = new SettingsRepository($app->cmsDb);
    $app->settings = new SettingsService($app->settingsRepo, $app->themeCatalog);
    $app->htmlSanitizer = new HtmlSanitizer();
    $app->seo = new SeoService($app->settings, $app->htmlSanitizer);
    $app->newsUploads = new NewsUploadService(BASE_DIR . '/public');
    $app->logoUploads = new LogoUploadService(BASE_DIR . '/public');
    $app->seoUploads = new SeoImageUploadService(BASE_DIR . '/public');
    $app->ticketUploads = new TicketUploadService(BASE_DIR . '/var/uploads/tickets');
    $app->news = new NewsRepository($app->cmsDb);
    $app->newsComments = new NewsCommentRepository($app->cmsDb);
    $app->tickets = new TicketRepository($app->cmsDb);
    $app->itemShopCategories = new ItemShopCategoryRepository($app->cmsDb);
    $app->itemShopProducts = new ItemShopProductRepository($app->cmsDb);
    $app->itemShopOrders = new ItemShopOrderRepository($app->cmsDb);
    $app->accountEmails = new AccountEmailRepository($app->cmsDb);
    $app->emailTokens = new EmailTokenRepository($app->cmsDb);
    $app->banRepo = new BanRepository($app->cmsDb);
    $app->economy = new EconomyRepository($app->cmsDb);
    $app->unstuckRepo = new UnstuckRepository($app->cmsDb);
    $app->events = new EventRepository($app->cmsDb);
    $app->serverChannels = new ServerChannelRepository($app->cmsDb);
    $app->downloads = new DownloadRepository($app->cmsDb);
    $app->downloadUploads = new DownloadUploadService(BASE_DIR . '/var/downloads');
    $app->banners = new BannerRepository($app->cmsDb);
    $app->bannerUploads = new BannerUploadService(
        BASE_DIR . '/public',
        new ImageVariantService(),
    );
    (new BannerSeedService(
        $app->banners,
        $app->bannerUploads,
        $app->settingsRepo,
        BASE_DIR . '/themes/default/assets/src',
    ))->seedIfNeeded();
    $app->cashPackages = new CashPackageRepository($app->cmsDb);
    $app->payments = new PaymentRepository($app->cmsDb);
    $app->paymentEvents = new PaymentEventRepository($app->cmsDb);
    $app->notifications = new NotificationRepository($app->cmsDb);
    $app->notificationService = new NotificationService($app->notifications);
    $app->mailer = new SymfonyMailer(
        $app->settings->mailFromAddress(),
        $app->settings->mailFromName(),
    );
    $app->paypal = new PayPalGateway($app->settings);
    $app->paymentGateways = new GatewayRegistry();
    $app->paymentGateways->register($app->paypal);

    $defaultLocale = $app->settings->defaultLocale();
    $app->translator = new Translator(BASE_DIR . '/lang', $app->locales->resolve($defaultLocale));
    $app->gameProfile = GameProfile::load();
    $app->protoSchemas = new ProtoSchemas($app->gameProfile);
    $app->protoEnums = new ProtoEnums($app->gameProfile);
    $app->itemStats = new ItemStats($app->protoEnums);
    $app->icons = new GameIconService(
        $app->gameProfile->path('icon_root'),
        BASE_DIR . '/var/cache/icons',
        $app->gameProfile,
        new ItemIconCatalog($app->gameProfile->path('item_list')),
    );
    $activeTheme = $app->settings->activeTheme();
    $app->theme = $app->createThemeEngine($activeTheme, $app->settings->registrationEnabled(), false);
    $app->theme->setSeoService($app->seo);
    $app->discord = new DiscordWebhookService($app->settings);
    $app->eventService = new EventService($app->events, $app->discord);
    $app->adminTheme = $app->createThemeEngine('admin', true, true);

    $app->db = new Database();
    $app->gameEconomyScan = new GameEconomyScanRepository($app->db);
    $app->accounts = new AccountRepository($app->db);
    $app->referralRepo = new ReferralRepository($app->cmsDb, $app->accounts);
    $app->players = new PlayerRepository($app->db);
    $app->items = new ItemRepository(
        $app->db,
        new ItemDescCatalog($app->gameProfile->path('itemdesc')),
        $app->itemStats,
    );
    $app->guilds = new GuildRepository($app->db);

    $themeWindowMinutes = $app->settings->onlineWindowMinutes();
    $themeDownloads = $app->downloads->listPublic();
    $app->theme->setGlobals([
        'has_news' => $app->news->countPublished() > 0,
        'captchaEnabled' => $app->settings->captchaPublicEnabled(),
        'discord_invite_url' => $app->settings->discordInviteUrl(),
        'site_title' => $app->settings->siteTitle(),
        'site_logo' => $app->settings->siteLogo(),
        'footer_text' => $app->settings->footerText(),
        'social_links' => $app->settings->socialLinksEnabled(),
        'news_show_views' => $app->settings->newsShowViews(),
        'theme_players_online' => $app->players->countActiveSinceMinutes($themeWindowMinutes),
        'theme_accounts_online' => $app->players->countAccountsActiveSinceMinutes($themeWindowMinutes),
        'theme_window_minutes' => $themeWindowMinutes,
        'theme_top_players' => $app->players->listRanking(1, 10),
        'theme_upcoming_events' => $app->events->upcomingPublished(4),
        'theme_client_download' => $themeDownloads[0] ?? null,
        'site_banners' => $app->banners->listEnabled(),
        'banner_settings' => $app->settings->bannerSettings(),
        'banners_seeded' => $app->settingsRepo->get('banners_seeded') === '1',
    ]);

    $app->gms = new GmRepository($app->db);
    $app->awards = new ItemAwardRepository($app->db);
    $app->shops = new ShopRepository($app->db);
    $app->refine = new RefineRepository($app->db);
    $app->logs = new LogRepository($app->db);
    $app->gameProto = new GameProtoService(
        $app->gameProfile,
        $app->protoSchemas,
        new ProtoNameRepository($app->db),
        new ProtoIndexCache(BASE_DIR . '/var/cache'),
    );
    $groupParser = new GroupTextParser();
    $app->mobDrops = new MobDropService(
        $app->gameProfile,
        $app->gameProto,
        $groupParser,
    );
    $app->dropFiles = new DropFileService(
        $app->gameProfile,
        $groupParser,
        new GroupTextWriter(),
    );
    $app->protoFields = new ProtoFormFields($app->translator, $app->protoEnums);
    $app->auth = new Auth($app->accounts);
    $app->banService = new BanService($app->banRepo, $app->accounts, $app->notificationService);
    $app->unstuckService = new UnstuckService($app->unstuckRepo, $app->players, $app->settings);
    $app->referralService = new ReferralService(
        $app->referralRepo,
        $app->accounts,
        $app->players,
        $app->settings,
    );
    $app->accountEmailService = new AccountEmailService(
        $app->accounts,
        $app->accountEmails,
        $app->emailTokens,
        $app->mailer,
        $app->settings,
    );
    $app->cashCredits = new CashCreditService(
        $app->payments,
        $app->accounts,
        $app->discord,
        $app->notificationService,
    );
    $app->paymentExpiry = new PaymentExpiryService(
        $app->payments,
        $app->paymentGateways,
        $app->notificationService,
    );
    $app->paymentStats = new PaymentStatsService($app->payments);
    $app->playerCensus = new PlayerCensusService(
        $app->accounts,
        $app->players,
        new Display($app->translator),
        $app->translator,
    );
    $app->paymentCheckout = new PaymentCheckoutService(
        $app->cashPackages,
        $app->payments,
        $app->paymentGateways,
        $app->settings,
        $app->notificationService,
        $app->paymentExpiry,
    );
    $app->paymentWebhookProcessor = new PaymentWebhookProcessor(
        $app->paymentEvents,
        $app->payments,
        $app->paymentGateways,
        $app->cashCredits,
    );
    $app->adminAudit = new AdminAuditService(
        new AdminAuditRepository($app->cmsDb),
        $app->adminAuth,
    );
    $app->itemShopPurchases = new ItemShopPurchaseService(
        $app->itemShopProducts,
        $app->itemShopOrders,
        $app->accounts,
        $app->awards,
        $app->notificationService,
    );
    $app->itemTooltips = new ItemTooltipBuilder(
        $app->gameProto,
        $app->itemStats,
        $app->protoEnums,
        new ItemDescCatalog($app->gameProfile->path('itemdesc')),
    );

    $accountId = $app->auth->id();
    $unread = 0;

    if ($accountId !== null) {
        try {
            $app->paymentExpiry->expireDue();
            $unread = $app->notifications->countUnread($accountId);
        } catch (\Throwable $e) {
            Log::error('payments', 'Pending payment expiry or notification count failed', $e);
        }
    }

    $app->theme->setGlobals(['notification_unread' => $unread]);
    $app->attachAdminNavCounts();
    AdminRuntime::bind($app->settings);
};
