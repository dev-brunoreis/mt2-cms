<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

use FastRoute\RouteCollector;
use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Http\Controller\AdminLegacyRedirectController;
use Mt2Cms\Http\Controller\AdminRolesController;

final class AdminLegacyRoutes
{
    public static function register(RouteCollector $r): void
    {
        foreach (array_keys(AdminPaths::legacyStaticMap()) as $oldPath) {
            if ($oldPath === '/admin/permissions') {
                continue;
            }

            $r->addRoute('GET', $oldPath, [AdminLegacyRedirectController::class, 'redirect']);
        }

        $r->addRoute('GET', '/admin/permissions', [AdminRolesController::class, 'legacyRedirect']);
        $r->addRoute('POST', '/admin/permissions', [AdminRolesController::class, 'legacyRedirect']);

        $r->addRoute('GET', '/admin/logs/{table:[a-z0-9_]+}', [AdminLegacyRedirectController::class, 'redirectLogTable']);
        $r->addRoute('GET', '/admin/owned-items/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectOwnedItem']);
        $r->addRoute('GET', '/admin/accounts/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectAccount']);
        $r->addRoute('GET', '/admin/characters/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectCharacter']);
        $r->addRoute('GET', '/admin/guilds/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectGuild']);
        $r->addRoute('GET', '/admin/tickets/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectTicket']);
        $r->addRoute('GET', '/admin/news/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/item-shop/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/drops/mob/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectDropMob']);
        $r->addRoute('GET', '/admin/shops/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/refine/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/gms/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/admins/{id:\d+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/roles/{slug:[a-z0-9-]+}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/{kind:items|mobs}', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/{kind:items|mobs}/new', [AdminLegacyRedirectController::class, 'redirect']);
        $r->addRoute('GET', '/admin/{kind:items|mobs}/{id:\d+}', [AdminLegacyRedirectController::class, 'redirectProto']);

        foreach ([
            '/admin/item-shop/categories/{id:\d+}',
            '/admin/item-shop/categories/{id:\d+}/item-search',
        ] as $pattern) {
            $r->addRoute('GET', $pattern, [AdminLegacyRedirectController::class, 'redirect']);
        }
    }
}
