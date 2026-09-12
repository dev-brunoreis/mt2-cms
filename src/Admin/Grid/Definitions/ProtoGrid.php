<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Service\GameProtoService;

final class ProtoGrid
{
    public static function definition(GameProtoService $protos, string $route): GridDefinition
    {
        $kind = $protos->kindFromRoute($route);
        $prefix = $route === GameProtoService::ROUTE_ITEMS ? 'admin.items' : 'admin.mobs';
        $columns = [];

        foreach ($protos->listColumns($kind) as $column) {
            if ($column === 'locale_name') {
                $columns[] = [
                    'key' => 'locale_name',
                    'label' => $prefix . '.fields.locale_name',
                    'sort' => 'locale_name',
                    'type' => 'icon_link',
                    'icon' => $route === GameProtoService::ROUTE_ITEMS ? 'item' : 'face',
                    'href' => self::listPath($route) . '/{id}',
                ];

                continue;
            }

            $columns[] = [
                'key' => $column,
                'label' => $prefix . '.fields.' . $column,
                'sort' => $column,
                'type' => in_array($column, ['vnum', 'level', 'rank'], true) ? 'number' : 'text',
            ];
        }

        $sortMap = [];

        foreach ($protos->listColumns($kind) as $column) {
            $sortMap[$column] = $column;
        }

        return GridDefinition::create(self::listPath($route), $prefix)
            ->idField('vnum')
            ->defaultSort('vnum', 'asc')
            ->orderBy($sortMap)
            ->columns($columns)
            ->massActions(self::listPath($route) . '/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => $prefix . '.confirm_mass_delete'],
            ]);
    }

    private static function listPath(string $route): string
    {
        return $route === GameProtoService::ROUTE_ITEMS
            ? '/admin/game-data/items'
            : '/admin/game-data/mobs';
    }
}
