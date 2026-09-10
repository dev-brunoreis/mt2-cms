<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

use FastRoute\RouteCollector;
use Mt2Cms\Http\Controller\AccountController;
use Mt2Cms\Http\Controller\AuthController;
use Mt2Cms\Http\Controller\GameIconController;
use Mt2Cms\Http\Controller\HomeController;
use Mt2Cms\Http\Controller\ItemShopController;
use Mt2Cms\Http\Controller\LocaleController;
use Mt2Cms\Http\Controller\NewsController;
use Mt2Cms\Http\Controller\PlayerController;
use Mt2Cms\Http\Controller\RankingController;
use Mt2Cms\Http\Controller\TicketController;

final class PublicRoutes
{
    public static function register(RouteCollector $r): void
    {
        $r->addRoute('GET', '/', [HomeController::class, 'index']);
        $r->addRoute('GET', '/login', [AuthController::class, 'showLogin']);
        $r->addRoute('POST', '/login', [AuthController::class, 'login']);
        $r->addRoute('GET', '/register', [AuthController::class, 'showRegister']);
        $r->addRoute('POST', '/register', [AuthController::class, 'register']);
        $r->addRoute('POST', '/logout', [AuthController::class, 'logout']);
        $r->addRoute('POST', '/locale', [LocaleController::class, 'update']);
        $r->addRoute('GET', '/account', [AccountController::class, 'index']);
        $r->addRoute('GET', '/account/characters', [AccountController::class, 'characters']);
        $r->addRoute('GET', '/account/tickets', [TicketController::class, 'index']);
        $r->addRoute('GET', '/account/tickets/new', [TicketController::class, 'create']);
        $r->addRoute('POST', '/account/tickets', [TicketController::class, 'store']);
        $r->addRoute('GET', '/account/tickets/{id:\d+}', [TicketController::class, 'show']);
        $r->addRoute('GET', '/account/tickets/{id:\d+}/attachments/{attachmentId:\d+}', [TicketController::class, 'downloadAttachment']);
        $r->addRoute('POST', '/account/tickets/{id:\d+}/reply', [TicketController::class, 'reply']);
        $r->addRoute('POST', '/account/tickets/{id:\d+}/close', [TicketController::class, 'close']);
        $r->addRoute('GET', '/news', [NewsController::class, 'index']);
        $r->addRoute('GET', '/news/{id:\d+}', [NewsController::class, 'show']);
        $r->addRoute('POST', '/news/{id:\d+}/comment', [NewsController::class, 'comment']);
        $r->addRoute('GET', '/shop', [ItemShopController::class, 'index']);
        $r->addRoute('POST', '/shop/buy', [ItemShopController::class, 'buy']);
        $r->addRoute('GET', '/ranking', [RankingController::class, 'index']);
        $r->addRoute('GET', '/player/{name}', [PlayerController::class, 'show']);
        $r->addRoute('GET', '/game/icon/{kind:item|face}/{id:\d+}', [GameIconController::class, 'show']);
    }
}
