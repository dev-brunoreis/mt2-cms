<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class AdminPermissions
{
    public const ROLE_SUPER = 'super';

    public static function isSuper(?string $role): bool
    {
        return strtolower(trim((string) $role)) === self::ROLE_SUPER;
    }
}
