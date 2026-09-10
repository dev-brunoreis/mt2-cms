<?php

declare(strict_types=1);

namespace Mt2Cms;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Mt2Cms\Admin\AdminSections;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Auth\SessionConfig;
use Mt2Cms\Auth\SessionGuard;
use Mt2Cms\Http\Controller\SetupController;
use Mt2Cms\Http\AdminRoutes;
use Mt2Cms\Http\PublicRoutes;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\AdminAuditRepository;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\CommonRepository;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\ItemAwardRepository;
use Mt2Cms\Repository\ItemRepository;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Repository\NewsCommentRepository;
use Mt2Cms\Repository\NewsRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Repository\ProtoNameRepository;
use Mt2Cms\Repository\RefineRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Repository\ItemShopCategoryRepository;
use Mt2Cms\Repository\ItemShopOrderRepository;
use Mt2Cms\Repository\ItemShopProductRepository;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Repository\TicketRepository;
use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\ItemDescCatalog;
use Mt2Cms\Game\ItemIconCatalog;
use Mt2Cms\Game\ItemStats;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Game\Drop\GroupTextParser;
use Mt2Cms\Game\Drop\GroupTextWriter;
use Mt2Cms\Repository\AclRepository;
use Mt2Cms\Repository\AdminRoleRepository;
use Mt2Cms\Repository\AdminTotpRepository;
use Mt2Cms\Service\AclService;
use Mt2Cms\Service\AdminAuditService;
use Mt2Cms\Service\DropFileService;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\ItemShopPurchaseService;
use Mt2Cms\Service\ItemTooltipBuilder;
use Mt2Cms\Service\MobDropService;
use Mt2Cms\Service\NewsUploadService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Service\TicketUploadService;
use Mt2Cms\Ban\BanRepository;
use Mt2Cms\Ban\BanService;
use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Mail\SymfonyMailer;
use Mt2Cms\Payment\PayPalGateway;
use Mt2Cms\Repository\AccountEmailRepository;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\DownloadRepository;
use Mt2Cms\Repository\EmailTokenRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\DownloadUploadService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Setup\CmsSchema;
use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\MigrationRunner;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Support\Log;
use Mt2Cms\Theme\AdminAclTwigExtension;
use Mt2Cms\Theme\ThemeEngine;

use function FastRoute\simpleDispatcher;

class Application
{
    use \Mt2Cms\Http\ControllerMap;
    private bool $installed;
    private Database $db;
    private Database $cmsDb;
    private Auth $auth;
    private AdminAuth $adminAuth;
    private Csrf $csrf;
    private Locales $locales;
    private Translator $translator;
    private ThemeEngine $theme;
    private ThemeEngine $adminTheme;
    private AccountRepository $accounts;
    private PlayerRepository $players;
    private ItemRepository $items;
    private GuildRepository $guilds;
    private CommonRepository $common;
    private ItemAwardRepository $awards;
    private ShopRepository $shops;
    private RefineRepository $refine;
    private LogRepository $logs;
    private GameProtoService $gameProto;
    private MobDropService $mobDrops;
    private DropFileService $dropFiles;
    private ?GameIconService $icons = null;
    private GameProfile $gameProfile;
    private ProtoSchemas $protoSchemas;
    private ProtoEnums $protoEnums;
    private ItemStats $itemStats;
    private ProtoFormFields $protoFields;
    private SettingsRepository $settingsRepo;
    private SettingsService $settings;
    private ThemeCatalog $themeCatalog;
    private NewsRepository $news;
    private NewsCommentRepository $newsComments;
    private TicketRepository $tickets;
    private ItemShopCategoryRepository $itemShopCategories;
    private ItemShopProductRepository $itemShopProducts;
    private ItemShopOrderRepository $itemShopOrders;
    private ItemShopPurchaseService $itemShopPurchases;
    private ItemTooltipBuilder $itemTooltips;
    private HtmlSanitizer $htmlSanitizer;
    private NewsUploadService $newsUploads;
    private TicketUploadService $ticketUploads;
    private AdminAuditService $adminAudit;
    private AclService $acl;
    private AdminRoleRepository $adminRoles;
    private AdminTotpRepository $adminTotp;
    private MailerInterface $mailer;
    private AccountEmailRepository $accountEmails;
    private EmailTokenRepository $emailTokens;
    private AccountEmailService $accountEmailService;
    private BanRepository $banRepo;
    private BanService $banService;
    private ServerChannelRepository $serverChannels;
    private DownloadRepository $downloads;
    private DownloadUploadService $downloadUploads;
    private CashPackageRepository $cashPackages;
    private PaymentRepository $payments;
    private PayPalGateway $paypal;
    private CashCreditService $cashCredits;
    private PaymentCheckoutService $paymentCheckout;

    public function __construct()
    {
        self::loadConfigs();
        $this->configureSession();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        SessionGuard::enforceIdleTimeout(SessionConfig::forRequestUri($uri));

        $this->installed = $this->isInstalled();
        $this->locales = new Locales(BASE_DIR . '/lang');
        $this->themeCatalog = new ThemeCatalog(BASE_DIR . '/themes');
        $this->csrf = new Csrf();

        if (!$this->installed) {
            $this->bootstrapSetup();

            return;
        }

        $this->bootstrapInstalled();
    }

    public function run(): void
    {
        try {
            if (!$this->installed) {
                $this->runSetupOnly();

                return;
            }

            $this->dispatch(function (RouteCollector $r): void {
                PublicRoutes::register($r);
                AdminRoutes::register($r);
            }, true);
        } catch (\Throwable $e) {
            Log::error('app', 'Unhandled exception', $e);
            Response::html('Internal Server Error', 500)->send();
        }
    }

    private function runSetupOnly(): void
    {
        try {
            $uri = $this->normalizeUri();

            if ($uri !== '/setup') {
                Response::redirect('/setup')->send();

                return;
            }

            $this->dispatch(function (RouteCollector $r): void {
                $r->addRoute('GET', '/setup', [SetupController::class, 'show']);
                $r->addRoute('POST', '/setup', [SetupController::class, 'submit']);
            }, false);
        } catch (\Throwable $e) {
            Log::error('app', 'Unhandled exception during setup', $e);
            Response::html('Internal Server Error', 500)->send();
        }
    }

    /**
     * @param \Closure(RouteCollector): void $registerRoutes
     */
    private function dispatch(callable $registerRoutes, bool $allowSetup404): void
    {
        $dispatcher = simpleDispatcher($registerRoutes);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->normalizeUri();

        if ($allowSetup404 && str_starts_with($uri, '/setup')) {
            Response::notFound($this->theme->render('player', [
                'title' => $this->translator->get('http.not_found'),
                'player' => null,
                'notFound' => true,
                'auth' => [
                    'check' => $this->auth->check(),
                    'login' => $this->auth->login(),
                    'user' => $this->auth->user(),
                ],
                'csrf' => $this->csrf->token(),
                'flash' => null,
            ]))->send();

            return;
        }

        $routeInfo = $dispatcher->dispatch($method, $uri);

        match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => Response::notFound($this->theme->render('player', [
                'title' => $this->translator->get('http.not_found'),
                'player' => null,
                'notFound' => true,
                'auth' => $this->authContext(),
                'csrf' => $this->csrf->token(),
                'flash' => null,
            ]))->send(),
            Dispatcher::METHOD_NOT_ALLOWED => Response::html(
                $this->translator->get('http.method_not_allowed'),
                405,
            )->send(),
            Dispatcher::FOUND => $this->invoke($routeInfo[1], $routeInfo[2])->send(),
        };
    }

    private function bootstrapSetup(): void
    {
        $this->translator = new Translator(BASE_DIR . '/lang', $this->locales->resolve('en'));
        $this->theme = $this->createThemeEngine('default', true, false);
        $this->auth = new Auth(new AccountRepository(new Database(['requirePassword' => false])));
    }

    private function bootstrapInstalled(): void
    {
        $this->assertAppKey();
        $this->cmsDb = Database::forCms();
        $this->assertSchemaCurrent();
        $this->adminAuth = new AdminAuth(new AdminRepository($this->cmsDb));
        $this->adminRoles = new AdminRoleRepository($this->cmsDb);
        $this->adminTotp = new AdminTotpRepository($this->cmsDb);
        $this->acl = new AclService(new AclRepository($this->cmsDb), $this->adminRoles);

        $this->settingsRepo = new SettingsRepository($this->cmsDb);
        $this->settings = new SettingsService($this->settingsRepo, $this->themeCatalog);
        $this->htmlSanitizer = new HtmlSanitizer();
        $this->newsUploads = new NewsUploadService(BASE_DIR . '/public');
        $this->ticketUploads = new TicketUploadService(BASE_DIR . '/var/uploads/tickets');
        $this->news = new NewsRepository($this->cmsDb);
        $this->newsComments = new NewsCommentRepository($this->cmsDb);
        $this->tickets = new TicketRepository($this->cmsDb);
        $this->itemShopCategories = new ItemShopCategoryRepository($this->cmsDb);
        $this->itemShopProducts = new ItemShopProductRepository($this->cmsDb);
        $this->itemShopOrders = new ItemShopOrderRepository($this->cmsDb);
        $this->accountEmails = new AccountEmailRepository($this->cmsDb);
        $this->emailTokens = new EmailTokenRepository($this->cmsDb);
        $this->banRepo = new BanRepository($this->cmsDb);
        $this->serverChannels = new ServerChannelRepository($this->cmsDb);
        $this->downloads = new DownloadRepository($this->cmsDb);
        $this->downloadUploads = new DownloadUploadService(BASE_DIR . '/var/downloads');
        $this->cashPackages = new CashPackageRepository($this->cmsDb);
        $this->payments = new PaymentRepository($this->cmsDb);
        $this->mailer = new SymfonyMailer(
            $this->settings->mailFromAddress(),
            $this->settings->mailFromName(),
        );
        $this->paypal = new PayPalGateway($this->settings);

        $defaultLocale = $this->settings->defaultLocale();
        $this->translator = new Translator(BASE_DIR . '/lang', $this->locales->resolve($defaultLocale));
        $this->gameProfile = GameProfile::load();
        $this->protoSchemas = new ProtoSchemas($this->gameProfile);
        $this->protoEnums = new ProtoEnums($this->gameProfile);
        $this->itemStats = new ItemStats($this->protoEnums);
        $this->icons = new GameIconService(
            $this->gameProfile->path('icon_root'),
            BASE_DIR . '/var/cache/icons',
            $this->gameProfile,
            new ItemIconCatalog($this->gameProfile->path('item_list')),
        );
        $activeTheme = $this->settings->activeTheme();
        $this->theme = $this->createThemeEngine($activeTheme, $this->settings->registrationEnabled(), false);
        $this->theme->setGlobals([
            'has_news' => $this->news->countPublished() > 0,
        ]);
        $this->adminTheme = $this->createThemeEngine('admin', true, true);

        $this->db = new Database();
        $this->accounts = new AccountRepository($this->db);
        $this->players = new PlayerRepository($this->db);
        $this->items = new ItemRepository(
            $this->db,
            new ItemDescCatalog($this->gameProfile->path('itemdesc')),
            $this->itemStats,
        );
        $this->guilds = new GuildRepository($this->db);
        $this->common = new CommonRepository($this->db);
        $this->awards = new ItemAwardRepository($this->db);
        $this->shops = new ShopRepository($this->db);
        $this->refine = new RefineRepository($this->db);
        $this->logs = new LogRepository($this->db);
        $this->gameProto = new GameProtoService(
            $this->gameProfile,
            $this->protoSchemas,
            new ProtoNameRepository($this->db),
        );
        $groupParser = new GroupTextParser();
        $this->mobDrops = new MobDropService(
            $this->gameProfile,
            $this->gameProto,
            $groupParser,
        );
        $this->dropFiles = new DropFileService(
            $this->gameProfile,
            $groupParser,
            new GroupTextWriter(),
        );
        $this->protoFields = new ProtoFormFields($this->translator, $this->protoEnums);
        $this->auth = new Auth($this->accounts);
        $this->banService = new BanService($this->banRepo, $this->accounts);
        $this->accountEmailService = new AccountEmailService(
            $this->accounts,
            $this->accountEmails,
            $this->emailTokens,
            $this->mailer,
            $this->settings,
        );
        $this->cashCredits = new CashCreditService($this->payments, $this->accounts);
        $this->paymentCheckout = new PaymentCheckoutService(
            $this->cashPackages,
            $this->payments,
            $this->paypal,
            $this->settings,
        );
        $this->adminAudit = new AdminAuditService(
            new AdminAuditRepository($this->cmsDb),
            $this->adminAuth,
        );
        $this->itemShopPurchases = new ItemShopPurchaseService(
            $this->itemShopProducts,
            $this->itemShopOrders,
            $this->accounts,
            $this->awards,
        );
        $this->itemTooltips = new ItemTooltipBuilder(
            $this->gameProto,
            $this->itemStats,
            $this->protoEnums,
            new ItemDescCatalog($this->gameProfile->path('itemdesc')),
        );
        $this->attachAdminNavCounts();
        \Mt2Cms\Admin\AdminRuntime::bind($this->settings);
    }

    private function createThemeEngine(string $activeTheme, bool $registrationEnabled, bool $isAdmin): ThemeEngine
    {
        $engine = new ThemeEngine(
            BASE_DIR . '/themes',
            $activeTheme,
            $this->translator,
            $this->locales->available(),
            $this->icons ?? null,
        );

        $globals = ['registration_enabled' => $registrationEnabled];

        if ($isAdmin) {
            $admin = $this->adminAuth->check() ? $this->adminAuth->user() : null;
            $globals['admin_sections'] = $this->acl->filterSections($admin, AdminSections::all());
            $engine->addExtension(new AdminAclTwigExtension($this->acl, $this->adminAuth));
        }

        $engine->setGlobals($globals);

        return $engine;
    }

    private function attachAdminNavCounts(): void
    {
        if (!$this->adminAuth->check()) {
            return;
        }

        $this->adminTheme->setGlobals([
            'admin_nav_counts' => [
                'news' => $this->newsComments->countPending(),
                'tickets' => $this->tickets->countOpen(),
            ],
        ]);
    }

    /**
     * @return array{check: bool, login: string|null, user: array<string, mixed>|null}
     */
    private function authContext(): array
    {
        return [
            'check' => $this->auth->check(),
            'login' => $this->auth->login(),
            'user' => $this->auth->user(),
        ];
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<string, string> $vars
     */
    private function invoke(array $handler, array $vars): Response
    {
        [$class, $method] = $handler;
        $controller = $this->resolveController($class);

        return $controller->{$method}(...$this->routeArguments($controller, $method, $vars));
    }

    /**
     * FastRoute always yields string captures; coerce to the action parameter types.
     *
     * @param array<string, string> $vars
     * @return list<mixed>
     */
    private function routeArguments(object $controller, string $method, array $vars): array
    {
        $args = [];

        foreach ((new \ReflectionMethod($controller, $method))->getParameters() as $param) {
            $name = $param->getName();

            if (!array_key_exists($name, $vars)) {
                break;
            }

            $args[] = $this->castRouteArgument($param, $vars[$name]);
        }

        return $args;
    }

    private function castRouteArgument(\ReflectionParameter $param, string $value): mixed
    {
        $type = $param->getType();

        if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    private function isInstalled(): bool
    {
        $value = self::getEnv()->get('APP_INSTALLED', '');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    private function normalizeUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        return rawurldecode($uri);
    }

    public static function getEnv(): Env
    {
        return Env::getInstance();
    }

    public static function loadConfigs(): void
    {
        Env::load();
    }

    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $config = SessionConfig::forRequestUri($uri);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.name', $config->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $config->path,
            'secure' => SessionConfig::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function assertSchemaCurrent(): void
    {
        $runner = new MigrationRunner($this->cmsDb);
        $current = $runner->readCurrentVersion();
        $latest = MigrationRunner::latestVersion();

        if ($current >= $latest) {
            return;
        }

        Response::html(
            'Service temporarily unavailable. Database schema is out of date. Run: php bin/migrate.php',
            503,
        )->send();
        exit;
    }

    private function assertAppKey(): void
    {
        if (\Mt2Cms\Support\AppCrypto::hasValidKey()) {
            return;
        }

        Response::html(
            'Service temporarily unavailable. APP_KEY is missing. Run: php bin/migrate.php',
            503,
        )->send();
        exit;
    }
}
