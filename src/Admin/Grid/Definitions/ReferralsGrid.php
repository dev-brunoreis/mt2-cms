<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid\Definitions;

use Mt2Cms\Admin\Grid\GridDefinition;

final class ReferralsGrid
{
    public static function definition(): GridDefinition
    {

        return GridDefinition::create('/admin/game/referrals', 'admin.referrals')
            ->defaultSort('created_at')
            ->orderBy([
                'id' => 'r.id',
                'referrer_login' => 'r.referrer_id',
                'referred_login' => 'r.referred_id',
                'created_at' => 'r.created_at',
                'rewarded_at' => 'r.rewarded_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.referrals.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'referrer_login', 'label' => 'admin.referrals.referrer', 'sort' => 'referrer_login', 'type' => 'text'],
                ['key' => 'referred_login', 'label' => 'admin.referrals.referred', 'sort' => 'referred_login', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'admin.referrals.created_at', 'sort' => 'created_at', 'type' => 'date'],
                ['key' => 'rewarded_at', 'label' => 'admin.referrals.rewarded_at', 'sort' => 'rewarded_at', 'type' => 'date'],
            ]);
        }
}
