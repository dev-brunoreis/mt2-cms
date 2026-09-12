<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Service\GameProtoService;

final class ProtoGrid
{
    public static function definition(GameProtoService $protos, ProtoEnums $enums, string $route): GridDefinition
    {
        $kind = $protos->kindFromRoute($route);
        $prefix = $route === GameProtoService::ROUTE_ITEMS ? 'admin.items' : 'admin.mobs';
        $columns = [];

        foreach ($protos->listColumns($kind) as $column) {
            if ($column === 'locale_name') {
                $nameColumn = [
                    'key' => 'locale_name',
                    'label' => $prefix . '.fields.locale_name',
                    'sort' => 'locale_name',
                    'type' => 'link',
                    'href' => self::listPath($route) . '/{id}',
                ];

                if ($route === GameProtoService::ROUTE_ITEMS) {
                    $nameColumn['type'] = 'icon_link';
                    $nameColumn['icon'] = 'item';
                }

                $columns[] = $nameColumn;

                continue;
            }

            $columns[] = self::valueColumn($kind, $prefix, $column, $enums);
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

    /**
     * @return array<string, mixed>
     */
    private static function valueColumn(string $kind, string $prefix, string $column, ProtoEnums $enums): array
    {
        if ($column === 'limit_value0') {
            return [
                'key' => 'limit_value0',
                'label' => $prefix . '.list_requirement',
                'sort' => 'limit_value0',
                'type' => 'proto_limit',
                'tokenKey' => 'limit_type0',
            ];
        }

        if ($column === 'apply_value0') {
            return [
                'key' => 'apply_value0',
                'label' => $prefix . '.list_bonus',
                'sort' => 'apply_value0',
                'type' => 'proto_apply',
                'tokenKey' => 'apply_type0',
            ];
        }

        if ($column === 'damage_min') {
            return [
                'key' => 'damage_min',
                'label' => $prefix . '.list_damage',
                'sort' => 'damage_min',
                'type' => 'range',
                'endKey' => 'damage_max',
            ];
        }

        $widget = $enums->widgetForField($kind, $column);

        return [
            'key' => $column,
            'label' => $prefix . '.fields.' . $column,
            'sort' => $column,
            'type' => match ($widget) {
                'select', 'subtype' => 'proto_token',
                'number' => 'number',
                default => 'text',
            },
        ];
    }

    private static function listPath(string $route): string
    {
        return $route === GameProtoService::ROUTE_ITEMS
            ? '/admin/game-data/items'
            : '/admin/game-data/mobs';
    }
}
