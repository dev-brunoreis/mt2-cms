<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class AwardsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/awards', 'admin.awards')
            ->orderBy([
                'id' => 'a.id',
                'login' => 'a.login',
                'vnum' => 'a.vnum',
                'count' => 'a.count',
                'status' => '(CASE WHEN a.taken_time IS NULL THEN 0 ELSE 1 END)',
                'given_time' => 'a.given_time',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.awards.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'login', 'label' => 'admin.awards.login', 'sort' => 'login', 'type' => 'text'],
                ['key' => 'vnum', 'label' => 'admin.awards.vnum', 'sort' => 'vnum', 'type' => 'number'],
                ['key' => 'item_name', 'label' => 'admin.awards.item', 'type' => 'text'],
                ['key' => 'count', 'label' => 'admin.awards.count_label', 'sort' => 'count', 'type' => 'number'],
                ['key' => 'status', 'label' => 'admin.awards.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'pending' => ['class' => 'admin-badge-warn', 'label' => 'admin.awards.status_pending'],
                    'taken' => ['class' => 'admin-badge-ok', 'label' => 'admin.awards.status_taken'],
                ]],
                ['key' => 'given_time', 'label' => 'admin.awards.given', 'sort' => 'given_time', 'type' => 'date'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.awards.status', 'type' => 'select', 'options' => [
                    'pending' => 'admin.awards.status_pending',
                    'taken' => 'admin.awards.status_taken',
                ]],
            ])
            ->massActions('/admin/game/awards/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.awards.confirm_mass_delete'],
            ]);
        }
}
