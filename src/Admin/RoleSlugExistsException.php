<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class RoleSlugExistsException extends \RuntimeException
{
    public function __construct(public readonly string $slug)
    {
        parent::__construct('admin.roles.slug_exists');
    }
}
