<?php

declare(strict_types=1);

namespace Mt2Cms;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Mt2Cms\Admin\AdminSections;
use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Controller\AccountController;
use Mt2Cms\Http\Controller\AdminAccountsController;
use Mt2Cms\Http\Controller\AdminAuthController;
use Mt2Cms\Http\Controller\AdminAwardsController;
use Mt2Cms\Http\Controller\AdminCharactersController;
use Mt2Cms\Http\Controller\AdminDashboardController;
use Mt2Cms\Http\Controller\AdminDropsController;
use Mt2Cms\Http\Controller\AdminGameProtoController;
use Mt2Cms\Http\Controller\AdminGmsController;
use Mt2Cms\Http\Controller\AdminGuildsController;
use Mt2Cms\Http\Controller\AdminLogsController;
use Mt2Cms\Http\Controller\AdminRefineController;
use Mt2Cms\Http\Controller\AdminSettingsController;
use Mt2Cms\Http\Controller\AdminShopsController;
use Mt2Cms\Http\Controller\AuthController;
use Mt2Cms\Http\Controller\GameIconController;
use Mt2Cms\Http\Controller\HomeController;
use Mt2Cms\Http\Controller\LocaleController;
use Mt2Cms\Http\Controller\PlayerController;
use Mt2Cms\Http\Controller\RankingController;
use Mt2Cms\Http\Controller\SetupController;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Locales;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\AdminRepository;
use Mt2Cms\Repository\CommonRepository;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\ItemAwardRepository;
use Mt2Cms\Repository\ItemRepository;
use Mt2Cms\Repository\LogRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Repository\ProtoNameRepository;
use Mt2Cms\Repository\RefineRepository;
use Mt2Cms\Repository\SettingsRepository;
use Mt2Cms\Repository\ShopRepository;
use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\ItemDescCatalog;
use Mt2Cms\Game\ItemIconCatalog;
use Mt2Cms\Game\ItemStats;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Game\Drop\GroupTextParser;
use Mt2Cms\Game\Drop\GroupTextWriter;
use Mt2Cms\Service\DropFileService;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Service\GameProtoService;
use Mt2Cms\Service\MobDropService;
use Mt2Cms\Service\SettingsService;
use Mt2Cms\Setup\EnvWriter;
use Mt2Cms\Setup\ThemeCatalog;
use Mt2Cms\Theme\ThemeEngine;

use function FastRoute\simpleDispatcher;

class Application
{
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

    public function __construct()
    {
        self::loadConfigs();
        $this->configureSession();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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
        if (!$this->installed) {
            $this->runSetupOnly();

            return;
        }

        $this->dispatch(function (RouteCollector $r): void {
            $r->addRoute('GET', '/', [HomeController::class, 'index']);
            $r->addRoute('GET', '/login', [AuthController::class, 'showLogin']);
            $r->addRoute('POST', '/login', [AuthController::class, 'login']);
            $r->addRoute('GET', '/register', [AuthController::class, 'showRegister']);
            $r->addRoute('POST', '/register', [AuthController::class, 'register']);
            $r->addRoute('POST', '/logout', [AuthController::class, 'logout']);
            $r->addRoute('POST', '/locale', [LocaleController::class, 'update']);
            $r->addRoute('GET', '/account', [AccountController::class, 'index']);
            $r->addRoute('GET', '/ranking', [RankingController::class, 'index']);
            $r->addRoute('GET', '/player/{name}', [PlayerController::class, 'show']);
            $r->addRoute('GET', '/game/icon/{kind:item|face}/{id:\d+}', [GameIconController::class, 'show']);

            $r->addRoute('GET', '/admin/login', [AdminAuthController::class, 'showLogin']);
            $r->addRoute('POST', '/admin/login', [AdminAuthController::class, 'login']);
            $r->addRoute('POST', '/admin/logout', [AdminAuthController::class, 'logout']);
            $r->addRoute('GET', '/admin', [AdminDashboardController::class, 'index']);
            $r->addRoute('GET', '/admin/registration', [AdminSettingsController::class, 'registration']);
            $r->addRoute('POST', '/admin/registration', [AdminSettingsController::class, 'saveRegistration']);
            $r->addRoute('GET', '/admin/themes', [AdminSettingsController::class, 'themes']);
            $r->addRoute('POST', '/admin/themes', [AdminSettingsController::class, 'saveThemes']);
            $r->addRoute('GET', '/admin/locale', [AdminSettingsController::class, 'locale']);
            $r->addRoute('POST', '/admin/locale', [AdminSettingsController::class, 'saveLocale']);
            $r->addRoute('GET', '/admin/accounts', [AdminAccountsController::class, 'index']);
            $r->addRoute('GET', '/admin/accounts/new', [AdminAccountsController::class, 'create']);
            $r->addRoute('POST', '/admin/accounts', [AdminAccountsController::class, 'store']);
            $r->addRoute('GET', '/admin/accounts/{id:\d+}', [AdminAccountsController::class, 'edit']);
            $r->addRoute('POST', '/admin/accounts/{id:\d+}', [AdminAccountsController::class, 'update']);
            $r->addRoute('POST', '/admin/accounts/{id:\d+}/block', [AdminAccountsController::class, 'block']);
            $r->addRoute('POST', '/admin/accounts/{id:\d+}/unblock', [AdminAccountsController::class, 'unblock']);
            $r->addRoute('POST', '/admin/accounts/{id:\d+}/delete', [AdminAccountsController::class, 'destroy']);
            $r->addRoute('GET', '/admin/characters', [AdminCharactersController::class, 'index']);
            $r->addRoute('GET', '/admin/characters/{id:\d+}', [AdminCharactersController::class, 'show']);
            $r->addRoute('GET', '/admin/owned-items/{id:\d+}', [AdminCharactersController::class, 'showOwnedItem']);
            $r->addRoute('GET', '/admin/guilds', [AdminGuildsController::class, 'index']);
            $r->addRoute('GET', '/admin/guilds/{id:\d+}', [AdminGuildsController::class, 'show']);
            $r->addRoute('POST', '/admin/guilds/{id:\d+}', [AdminGuildsController::class, 'update']);
            $r->addRoute('POST', '/admin/guilds/{id:\d+}/kick', [AdminGuildsController::class, 'kick']);
            $r->addRoute('POST', '/admin/guilds/{id:\d+}/comment/{commentId:\d+}/delete', [AdminGuildsController::class, 'deleteComment']);
            $r->addRoute('POST', '/admin/guilds/{id:\d+}/dissolve', [AdminGuildsController::class, 'dissolve']);
            $r->addRoute('GET', '/admin/awards', [AdminAwardsController::class, 'index']);
            $r->addRoute('GET', '/admin/awards/new', [AdminAwardsController::class, 'create']);
            $r->addRoute('POST', '/admin/awards', [AdminAwardsController::class, 'store']);
            $r->addRoute('POST', '/admin/awards/{id:\d+}/delete', [AdminAwardsController::class, 'destroy']);
            $r->addRoute('GET', '/admin/shops', [AdminShopsController::class, 'index']);
            $r->addRoute('GET', '/admin/shops/new', [AdminShopsController::class, 'create']);
            $r->addRoute('POST', '/admin/shops', [AdminShopsController::class, 'store']);
            $r->addRoute('GET', '/admin/shops/{id:\d+}', [AdminShopsController::class, 'edit']);
            $r->addRoute('POST', '/admin/shops/{id:\d+}', [AdminShopsController::class, 'update']);
            $r->addRoute('POST', '/admin/shops/{id:\d+}/delete', [AdminShopsController::class, 'destroy']);
            $r->addRoute('POST', '/admin/shops/{id:\d+}/items', [AdminShopsController::class, 'addItem']);
            $r->addRoute('POST', '/admin/shops/{id:\d+}/items/delete', [AdminShopsController::class, 'removeItem']);
            $r->addRoute('GET', '/admin/refine', [AdminRefineController::class, 'index']);
            $r->addRoute('GET', '/admin/refine/new', [AdminRefineController::class, 'create']);
            $r->addRoute('POST', '/admin/refine', [AdminRefineController::class, 'store']);
            $r->addRoute('GET', '/admin/refine/{id:\d+}', [AdminRefineController::class, 'edit']);
            $r->addRoute('POST', '/admin/refine/{id:\d+}', [AdminRefineController::class, 'update']);
            $r->addRoute('POST', '/admin/refine/{id:\d+}/delete', [AdminRefineController::class, 'destroy']);
            $r->addRoute('GET', '/admin/drops', [AdminDropsController::class, 'index']);
            $r->addRoute('GET', '/admin/drops/etc', [AdminDropsController::class, 'etc']);
            $r->addRoute('POST', '/admin/drops/etc', [AdminDropsController::class, 'saveEtc']);
            $r->addRoute('GET', '/admin/drops/common', [AdminDropsController::class, 'common']);
            $r->addRoute('POST', '/admin/drops/common', [AdminDropsController::class, 'saveCommon']);
            $r->addRoute('GET', '/admin/drops/mob/{id:\d+}', [AdminDropsController::class, 'mob']);
            $r->addRoute('POST', '/admin/drops/mob/{id:\d+}', [AdminDropsController::class, 'saveMob']);
            $r->addRoute('GET', '/admin/gms', [AdminGmsController::class, 'index']);
            $r->addRoute('GET', '/admin/gms/new', [AdminGmsController::class, 'create']);
            $r->addRoute('POST', '/admin/gms', [AdminGmsController::class, 'store']);
            $r->addRoute('GET', '/admin/gms/{id:\d+}', [AdminGmsController::class, 'edit']);
            $r->addRoute('POST', '/admin/gms/{id:\d+}', [AdminGmsController::class, 'update']);
            $r->addRoute('POST', '/admin/gms/{id:\d+}/delete', [AdminGmsController::class, 'destroy']);
            $r->addRoute('POST', '/admin/gms/hosts', [AdminGmsController::class, 'addHost']);
            $r->addRoute('POST', '/admin/gms/hosts/delete', [AdminGmsController::class, 'deleteHost']);
            $r->addRoute('GET', '/admin/{kind:items|mobs}', [AdminGameProtoController::class, 'index']);
            $r->addRoute('GET', '/admin/{kind:items|mobs}/new', [AdminGameProtoController::class, 'create']);
            $r->addRoute('POST', '/admin/{kind:items|mobs}', [AdminGameProtoController::class, 'store']);
            $r->addRoute('GET', '/admin/{kind:items|mobs}/{id:\d+}', [AdminGameProtoController::class, 'edit']);
            $r->addRoute('POST', '/admin/{kind:items|mobs}/{id:\d+}', [AdminGameProtoController::class, 'update']);
            $r->addRoute('POST', '/admin/{kind:items|mobs}/{id:\d+}/delete', [AdminGameProtoController::class, 'destroy']);
            $r->addRoute('GET', '/admin/logs/{table:[a-z0-9_]+}', [AdminLogsController::class, 'show']);
        }, true);
    }

    private function runSetupOnly(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $this->normalizeUri();

        if ($uri !== '/setup') {
            Response::redirect('/setup')->send();

            return;
        }

        $this->dispatch(function (RouteCollector $r): void {
            $r->addRoute('GET', '/setup', [SetupController::class, 'show']);
            $r->addRoute('POST', '/setup', [SetupController::class, 'submit']);
        }, false);
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
        $this->cmsDb = Database::forCms();
        $this->settingsRepo = new SettingsRepository($this->cmsDb);
        $this->settings = new SettingsService($this->settingsRepo, $this->themeCatalog);

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
        $this->adminAuth = new AdminAuth(new AdminRepository($this->cmsDb));
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
            $globals['admin_sections'] = AdminSections::all();
        }

        $engine->setGlobals($globals);

        return $engine;
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

        return $controller->{$method}(...array_values($vars));
    }

    private function resolveController(string $class): object
    {
        return match ($class) {
            HomeController::class => new HomeController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
            ),
            AuthController::class => new AuthController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->accounts,
                $this->settings,
            ),
            AccountController::class => new AccountController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->players,
            ),
            RankingController::class => new RankingController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->players,
            ),
            PlayerController::class => new PlayerController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->players,
            ),
            GameIconController::class => new GameIconController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->icons,
            ),
            LocaleController::class => new LocaleController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->locales,
            ),
            SetupController::class => new SetupController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->themeCatalog,
                new EnvWriter(),
            ),
            AdminAuthController::class => new AdminAuthController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
            ),
            AdminDashboardController::class => new AdminDashboardController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->players,
            ),
            AdminSettingsController::class => new AdminSettingsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->settings,
                $this->themeCatalog,
                $this->locales,
            ),
            AdminAccountsController::class => new AdminAccountsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->accounts,
                $this->players,
                $this->logs,
            ),
            AdminCharactersController::class => new AdminCharactersController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->players,
                $this->items,
                $this->guilds,
                $this->logs,
                $this->accounts,
            ),
            AdminGameProtoController::class => new AdminGameProtoController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->gameProto,
                $this->protoFields,
                $this->mobDrops,
                $this->protoEnums,
            ),
            AdminLogsController::class => new AdminLogsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->logs,
            ),
            AdminGuildsController::class => new AdminGuildsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->guilds,
            ),
            AdminGmsController::class => new AdminGmsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->common,
                $this->accounts,
            ),
            AdminAwardsController::class => new AdminAwardsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->awards,
                $this->accounts,
                $this->players,
                $this->gameProto,
            ),
            AdminShopsController::class => new AdminShopsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->shops,
                $this->gameProto,
            ),
            AdminRefineController::class => new AdminRefineController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->refine,
                $this->gameProto,
            ),
            AdminDropsController::class => new AdminDropsController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->translator,
                $this->adminAuth,
                $this->adminTheme,
                $this->dropFiles,
                $this->mobDrops,
                $this->gameProto,
                $this->gameProfile,
            ),
            default => throw new \RuntimeException('Unknown controller: ' . $class),
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

        $https = $_SERVER['HTTPS'] ?? '';
        $secure = $https !== '' && $https !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
    }
}
