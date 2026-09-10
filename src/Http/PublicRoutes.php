<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

use FastRoute\RouteCollector;
use Mt2Cms\Http\Controller\AccountController;
use Mt2Cms\Http\Controller\CaptchaController;
use Mt2Cms\Http\Controller\AuthController;
use Mt2Cms\Http\Controller\DonateController;
use Mt2Cms\Http\Controller\DownloadsController;
use Mt2Cms\Http\Controller\EmailVerificationController;
use Mt2Cms\Http\Controller\GameIconController;
use Mt2Cms\Http\Controller\HomeController;
use Mt2Cms\Http\Controller\ItemShopController;
use Mt2Cms\Http\Controller\LocaleController;
use Mt2Cms\Http\Controller\NewsController;
use Mt2Cms\Http\Controller\PasswordController;
use Mt2Cms\Http\Controller\PaymentWebhookController;
use Mt2Cms\Http\Controller\PlayerController;
use Mt2Cms\Http\Controller\RankingController;
use Mt2Cms\Http\Controller\StatusController;
use Mt2Cms\Http\Controller\TicketController;

final class PublicRoutes
{
    public static function register(RouteCollector $r): void
    {
        $r->addRoute('GET', '/', [HomeController::class, 'index']);
        $r->addRoute('GET', '/captcha.svg', [CaptchaController::class, 'publicSvg']);
        $r->addRoute('GET', '/login', [AuthController::class, 'showLogin']);
        $r->addRoute('POST', '/login', [AuthController::class, 'login']);
        $r->addRoute('GET', '/register', [AuthController::class, 'showRegister']);
        $r->addRoute('POST', '/register', [AuthController::class, 'register']);
        $r->addRoute('POST', '/logout', [AuthController::class, 'logout']);
        $r->addRoute('GET', '/forgot-password', [PasswordController::class, 'showForgot']);
        $r->addRoute('POST', '/forgot-password', [PasswordController::class, 'forgot']);
        $r->addRoute('GET', '/reset-password/{token}', [PasswordController::class, 'showReset']);
        $r->addRoute('POST', '/reset-password/{token}', [PasswordController::class, 'reset']);
        $r->addRoute('GET', '/verify-email/{token}', [EmailVerificationController::class, 'verify']);
        $r->addRoute('POST', '/account/verify-email/resend', [EmailVerificationController::class, 'resend']);
        $r->addRoute('POST', '/locale', [LocaleController::class, 'update']);
        $r->addRoute('GET', '/account', [AccountController::class, 'index']);
        $r->addRoute('GET', '/account/characters', [AccountController::class, 'characters']);
        $r->addRoute('GET', '/account/password', [AccountController::class, 'showPassword']);
        $r->addRoute('POST', '/account/password', [AccountController::class, 'updatePassword']);
        $r->addRoute('GET', '/account/email', [AccountController::class, 'showEmail']);
        $r->addRoute('POST', '/account/email', [AccountController::class, 'updateEmail']);
        $r->addRoute('GET', '/account/pin', [AccountController::class, 'showPin']);
        $r->addRoute('POST', '/account/pin', [AccountController::class, 'updatePin']);
        $r->addRoute('GET', '/account/orders', [AccountController::class, 'orders']);
        $r->addRoute('GET', '/account/payments', [AccountController::class, 'payments']);
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
        $r->addRoute('GET', '/status', [StatusController::class, 'index']);
        $r->addRoute('GET', '/downloads', [DownloadsController::class, 'index']);
        $r->addRoute('GET', '/downloads/{id:\d+}/file', [DownloadsController::class, 'file']);
        $r->addRoute('GET', '/donate', [DonateController::class, 'index']);
        $r->addRoute('POST', '/donate/buy', [DonateController::class, 'buy']);
        $r->addRoute('GET', '/donate/return', [DonateController::class, 'returnUrl']);
        $r->addRoute('GET', '/donate/cancel', [DonateController::class, 'cancel']);
        $r->addRoute('POST', '/payments/webhook/paypal', [PaymentWebhookController::class, 'paypal']);
        $r->addRoute('GET', '/ranking', [RankingController::class, 'index']);
        $r->addRoute('GET', '/player/{name}', [PlayerController::class, 'show']);
        $r->addRoute('GET', '/game/icon/{kind:item|face}/{id:\d+}', [GameIconController::class, 'show']);
    }
}
