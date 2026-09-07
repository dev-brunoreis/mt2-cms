<?php

declare(strict_types=1);

namespace Mt2Cms;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Http\Controller\AccountController;
use Mt2Cms\Http\Controller\AuthController;
use Mt2Cms\Http\Controller\HomeController;
use Mt2Cms\Http\Controller\PlayerController;
use Mt2Cms\Http\Controller\RankingController;
use Mt2Cms\Http\Response;
use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Theme\ThemeEngine;

use function FastRoute\simpleDispatcher;

class Application
{
    private Database $db;
    private Auth $auth;
    private Csrf $csrf;
    private ThemeEngine $theme;
    private AccountRepository $accounts;
    private PlayerRepository $players;

    public function __construct()
    {
        self::loadConfigs();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->db = new Database();
        $this->accounts = new AccountRepository($this->db);
        $this->players = new PlayerRepository($this->db);
        $this->auth = new Auth($this->accounts);
        $this->csrf = new Csrf();

        $themeName = (string) (self::getEnv()->get('THEME', 'default') ?: 'default');
        $this->theme = new ThemeEngine(BASE_DIR . '/themes', $themeName);
    }

    public function run(): void
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            $r->addRoute('GET', '/', [HomeController::class, 'index']);
            $r->addRoute('GET', '/login', [AuthController::class, 'showLogin']);
            $r->addRoute('POST', '/login', [AuthController::class, 'login']);
            $r->addRoute('GET', '/register', [AuthController::class, 'showRegister']);
            $r->addRoute('POST', '/register', [AuthController::class, 'register']);
            $r->addRoute('POST', '/logout', [AuthController::class, 'logout']);
            $r->addRoute('GET', '/account', [AccountController::class, 'index']);
            $r->addRoute('GET', '/ranking', [RankingController::class, 'index']);
            $r->addRoute('GET', '/player/{name}', [PlayerController::class, 'show']);
        });

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        $uri = rawurldecode($uri);
        $routeInfo = $dispatcher->dispatch($method, $uri);

        match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => Response::notFound($this->theme->render('player', [
                'title' => 'Not Found',
                'player' => null,
                'notFound' => true,
                'auth' => [
                    'check' => $this->auth->check(),
                    'login' => $this->auth->login(),
                    'user' => $this->auth->user(),
                ],
                'csrf' => $this->csrf->token(),
                'flash' => null,
            ]))->send(),
            Dispatcher::METHOD_NOT_ALLOWED => Response::html('Method Not Allowed', 405)->send(),
            Dispatcher::FOUND => $this->invoke($routeInfo[1], $routeInfo[2])->send(),
        };
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
            HomeController::class => new HomeController($this->theme, $this->auth, $this->csrf),
            AuthController::class => new AuthController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->accounts,
            ),
            AccountController::class => new AccountController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->players,
            ),
            RankingController::class => new RankingController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->players,
            ),
            PlayerController::class => new PlayerController(
                $this->theme,
                $this->auth,
                $this->csrf,
                $this->players,
            ),
            default => throw new \RuntimeException('Unknown controller: ' . $class),
        };
    }

    public static function getEnv(): Env
    {
        return Env::getInstance();
    }

    public static function loadConfigs(): void
    {
        Env::load();
    }
}
