<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class RoleSlug
{
    public static function fromLabel(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new \InvalidArgumentException('admin.roles.invalid_slug');
        }

        if (strlen($slug) > 32) {
            $slug = substr($slug, 0, 32);
            $slug = rtrim($slug, '-');
        }

        if ($slug === '' || AdminPermissions::isSuper($slug)) {
            throw new \InvalidArgumentException('admin.roles.invalid_slug');
        }

        return $slug;
    }

    public static function assertValid(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if ($slug === '' || strlen($slug) > 32 || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new \InvalidArgumentException('admin.roles.invalid_slug');
        }

        if (AdminPermissions::isSuper($slug)) {
            throw new \InvalidArgumentException('admin.roles.reserved_slug');
        }

        return $slug;
    }
}
