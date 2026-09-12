<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class RefineGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game-data/refine', 'admin.refine')
            ->orderBy([
                'id' => 'r.id',
                'cost' => 'r.cost',
                'prob' => 'r.prob',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.refine.id', 'sort' => 'id', 'type' => 'link', 'href' => '/admin/game-data/refine/{id}'],
                ['key' => 'source_label', 'label' => 'admin.refine.source', 'type' => 'text'],
                ['key' => 'result_label', 'label' => 'admin.refine.result', 'type' => 'text'],
                ['key' => 'cost', 'label' => 'admin.refine.cost', 'sort' => 'cost', 'type' => 'number'],
                ['key' => 'prob', 'label' => 'admin.refine.prob', 'sort' => 'prob', 'type' => 'number'],
            ])
            ->massActions('/admin/game-data/refine/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.refine.confirm_mass_delete'],
            ]);
        }
}
