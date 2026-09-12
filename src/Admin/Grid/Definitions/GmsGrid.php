<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class GmsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game-data/gms', 'admin.gms')
            ->idField('mID')
            ->orderBy([
                'mID' => 'mID',
                'mAccount' => 'mAccount',
                'mName' => 'mName',
                'mAuthority' => 'mAuthority',
            ])
            ->columns([
                ['key' => 'mID', 'label' => 'admin.gms.id', 'sort' => 'mID', 'type' => 'muted'],
                ['key' => 'mAccount', 'label' => 'admin.gms.account', 'sort' => 'mAccount', 'type' => 'link', 'href' => '/admin/game-data/gms/{mID}'],
                ['key' => 'mName', 'label' => 'admin.gms.name', 'sort' => 'mName', 'type' => 'text'],
                ['key' => 'mAuthority', 'label' => 'admin.gms.authority', 'sort' => 'mAuthority', 'type' => 'text'],
            ])
            ->massActions('/admin/game-data/gms/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.gms.confirm_mass_delete'],
            ]);
        }
}
