<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Http\Response;

final class AdminLegacyRedirectController extends Controller
{
    public function redirect(): Response
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = $uri;

        if (false !== $pos = strpos($path, '?')) {
            $path = substr($path, 0, $pos);
        }

        $path = rawurldecode($path);
        $target = AdminPaths::resolveLegacyRedirect($path);

        if ($target === null) {
            return Response::html('Not Found', 404);
        }

        $query = '';

        if (false !== $pos = strpos($uri, '?')) {
            $query = substr($uri, $pos);
        }

        return Response::redirect($target . $query, 302);
    }

    public function redirectOwnedItem(int $id): Response
    {
        return Response::redirect(AdminPaths::gameOwnedItem($id), 302);
    }

    public function redirectGuild(int $id): Response
    {
        return Response::redirect(AdminPaths::gameGuilds() . '/' . $id, 302);
    }

    public function redirectCharacter(int $id): Response
    {
        return Response::redirect(AdminPaths::gameCharacters() . '/' . $id, 302);
    }

    public function redirectAccount(int $id): Response
    {
        return Response::redirect(AdminPaths::gameAccounts() . '/' . $id, 302);
    }

    public function redirectTicket(int $id): Response
    {
        return Response::redirect(AdminPaths::contentTickets() . '/' . $id, 302);
    }

    public function redirectDropMob(int $id): Response
    {
        return Response::redirect(AdminPaths::gameDataDrops() . '/mob/' . $id, 302);
    }

    public function redirectProto(string $kind, int $id): Response
    {
        $base = $kind === 'items' ? AdminPaths::gameDataItems() : AdminPaths::gameDataMobs();

        return Response::redirect($base . '/' . $id, 302);
    }

    public function redirectStoreCategoryPath(string $suffix): Response
    {
        return Response::redirect('/admin/store/categories/' . ltrim($suffix, '/'), 302);
    }

    public function redirectLogTable(string $table): Response
    {
        return Response::redirect(AdminPaths::logs($table), 302);
    }
}
