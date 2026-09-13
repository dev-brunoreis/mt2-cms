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
use Mt2Cms\Support\Database;
use Mt2Cms\Support\Env;
use Mt2Cms\Support\Money;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\AdminAuditRepository;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Game\Proto\ProtoIndexCache;
use Mt2Cms\Repository\GmRepository;
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
use Mt2Cms\Service\LogoUploadService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Service\TicketUploadService;
use Mt2Cms\Repository\BanRepository;
use Mt2Cms\Service\BanService;
use Mt2Cms\Repository\EconomyRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Repository\ReferralRepository;
use Mt2Cms\Service\ReferralService;
use Mt2Cms\Repository\UnstuckRepository;
use Mt2Cms\Service\UnstuckService;
use Mt2Cms\Service\DiscordWebhookService;
use Mt2Cms\Repository\EventRepository;
use Mt2Cms\Service\EventService;
use Mt2Cms\Mail\MailerInterface;
use Mt2Cms\Mail\SymfonyMailer;
use Mt2Cms\Payment\GatewayRegistry;
use Mt2Cms\Payment\PayPalGateway;
use Mt2Cms\Repository\AccountEmailRepository;
use Mt2Cms\Repository\CashPackageRepository;
use Mt2Cms\Repository\DownloadRepository;
use Mt2Cms\Repository\BannerRepository;
use Mt2Cms\Repository\EmailTokenRepository;
use Mt2Cms\Repository\NotificationRepository;
use Mt2Cms\Repository\PaymentEventRepository;
use Mt2Cms\Repository\PaymentRepository;
use Mt2Cms\Repository\ServerChannelRepository;
use Mt2Cms\Service\AccountEmailService;
use Mt2Cms\Service\CashCreditService;
use Mt2Cms\Service\DownloadUploadService;
use Mt2Cms\Service\BannerUploadService;
use Mt2Cms\Service\NotificationService;
use Mt2Cms\Service\PaymentCheckoutService;
use Mt2Cms\Service\PaymentExpiryService;
use Mt2Cms\Service\PaymentStatsService;
use Mt2Cms\Service\PaymentWebhookProcessor;
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

    /** True when APP_INSTALLED is set but the admins table has no rows. */
    public bool $needsAdminRecovery = false;

    /** DI container — public for controller_factories.php */
    public Database $db;
    public Database $cmsDb;
    public Auth $auth;
    public AdminAuth $adminAuth;
    public Csrf $csrf;
    public Locales $locales;
    public Translator $translator;
    public ThemeEngine $theme;
    public ThemeEngine $adminTheme;
    public AccountRepository $accounts;
    public PlayerRepository $players;
    public ItemRepository $items;
    public GuildRepository $guilds;
    public GmRepository $gms;
    public ItemAwardRepository $awards;
    public ShopRepository $shops;
    public RefineRepository $refine;
    public LogRepository $logs;
    public GameProtoService $gameProto;
    public MobDropService $mobDrops;
    public DropFileService $dropFiles;
    public ?GameIconService $icons = null;
    public GameProfile $gameProfile;
    public ProtoSchemas $protoSchemas;
    public ProtoEnums $protoEnums;
    public ItemStats $itemStats;
    public ProtoFormFields $protoFields;
    public SettingsRepository $settingsRepo;
    public SettingsService $settings;
    public ThemeCatalog $themeCatalog;
    public NewsRepository $news;
    public NewsCommentRepository $newsComments;
    public TicketRepository $tickets;
    public ItemShopCategoryRepository $itemShopCategories;
    public ItemShopProductRepository $itemShopProducts;
    public ItemShopOrderRepository $itemShopOrders;
    public ItemShopPurchaseService $itemShopPurchases;
    public ItemTooltipBuilder $itemTooltips;
    public HtmlSanitizer $htmlSanitizer;
    public NewsUploadService $newsUploads;
    public LogoUploadService $logoUploads;
    public TicketUploadService $ticketUploads;
    public AdminAuditService $adminAudit;
    public AclService $acl;
    public AdminRoleRepository $adminRoles;
    public AdminTotpRepository $adminTotp;
    public MailerInterface $mailer;
    public AccountEmailRepository $accountEmails;
    public EmailTokenRepository $emailTokens;
    public AccountEmailService $accountEmailService;
    public BanRepository $banRepo;
    public BanService $banService;
    public UnstuckRepository $unstuckRepo;
    public UnstuckService $unstuckService;
    public ReferralRepository $referralRepo;
    public ReferralService $referralService;
    public ServerChannelRepository $serverChannels;
    public DownloadRepository $downloads;
    public DownloadUploadService $downloadUploads;
    public BannerRepository $banners;
    public BannerUploadService $bannerUploads;
    public CashPackageRepository $cashPackages;
    public PaymentRepository $payments;
    public PaymentEventRepository $paymentEvents;
    public PaymentWebhookProcessor $paymentWebhookProcessor;
    public NotificationRepository $notifications;
    public NotificationService $notificationService;
    public PayPalGateway $paypal;
    public GatewayRegistry $paymentGateways;
    public CashCreditService $cashCredits;
    public PaymentCheckoutService $paymentCheckout;
    public PaymentExpiryService $paymentExpiry;
    public PaymentStatsService $paymentStats;
    public EventRepository $events;
    public EventService $eventService;
    public DiscordWebhookService $discord;
    public EconomyRepository $economy;
    public GameEconomyScanRepository $gameEconomyScan;

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

            if ($this->needsAdminRecovery) {
                $this->runAdminRecoverySetup();

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
        $this->runSetupRoutes('/setup');
    }

    private function runAdminRecoverySetup(): void
    {
        $this->runSetupRoutes('/setup?step=admin');
    }

    private function runSetupRoutes(string $redirectTarget): void
    {
        try {
            $uri = $this->normalizeUri();

            if ($uri !== '/setup') {
                Response::redirect($redirectTarget)->send();

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
        (require __DIR__ . '/bootstrap/setup_services.php')($this);
    }

    private function bootstrapInstalled(): void
    {
        $this->assertAppKey();
        $this->cmsDb = Database::forCms();
        $this->assertSchemaCurrent();
        (require __DIR__ . '/bootstrap/installed_services.php')($this);
    }

    public function createThemeEngine(string $activeTheme, bool $registrationEnabled, bool $isAdmin): ThemeEngine
    {
        $engine = new ThemeEngine(
            BASE_DIR . '/themes',
            $activeTheme,
            $this->translator,
            $this->locales->available(),
            $this->icons ?? null,
            isset($this->settings) ? $this->settings->moneyFormat() : Money::FORMAT_DOT,
        );

        $globals = ['registration_enabled' => $registrationEnabled];

        if ($isAdmin) {
            $admin = $this->adminAuth->check() ? $this->adminAuth->user() : null;
            $sections = $this->acl->filterSections($admin, AdminSections::all());
            $globals['admin_sections'] = $sections;
            $globals['admin_pinned_nav'] = AdminSections::pinnedNavItem($sections);
            $engine->addExtension(new AdminAclTwigExtension($this->acl, $this->adminAuth));
        }

        $engine->setGlobals($globals);

        return $engine;
    }

    public function attachAdminNavCounts(): void
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

        $sessionDir = dirname(__DIR__) . '/var/sessions';

        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0750, true);
        }

        ini_set('session.save_path', $sessionDir);
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

        Log::error(
            'app',
            sprintf(
                'Database schema is out of date (current=%d, latest=%d). Run: php bin/migrate.php',
                $current,
                $latest,
            ),
        );
        $this->serviceUnavailable();
    }

    private function assertAppKey(): void
    {
        if (\Mt2Cms\Support\AppCrypto::hasValidKey()) {
            return;
        }

        Log::error('app', 'APP_KEY is missing or invalid. Run: php bin/migrate.php');
        $this->serviceUnavailable();
    }

    private function serviceUnavailable(): void
    {
        Response::html('Service temporarily unavailable', 503)->send();
        exit;
    }
}
