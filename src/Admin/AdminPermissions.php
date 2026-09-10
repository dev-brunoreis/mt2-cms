<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

final class AdminPermissions
{
    public const ROLE_SUPER = 'super';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_CONTENT = 'content';

    /** @var list<string> */
    private const SUPPORT_SECTIONS = [
        'dashboard',
        'accounts',
        'characters',
        'guilds',
        'awards',
        'tickets',
    ];

    /** @var list<string> */
    private const CONTENT_SECTIONS = [
        'dashboard',
        'news',
        'news-comments',
        'news-settings',
        'item-shop',
        'item-shop-categories',
        'item-shop-orders',
        'tickets',
        'registration',
        'themes',
        'locale',
    ];

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            self::ROLE_SUPPORT => self::ROLE_SUPPORT,
            self::ROLE_CONTENT => self::ROLE_CONTENT,
            default => self::ROLE_SUPER,
        };
    }

    public static function canAccessSection(string $role, string $sectionId): bool
    {
        $role = self::normalizeRole($role);

        if ($role === self::ROLE_SUPER) {
            return true;
        }

        if (str_starts_with($sectionId, 'log-')) {
            return $role === self::ROLE_SUPPORT;
        }

        $allowed = $role === self::ROLE_SUPPORT ? self::SUPPORT_SECTIONS : self::CONTENT_SECTIONS;

        return in_array($sectionId, $allowed, true);
    }

    /**
     * @return list<array{id: string, label: string, collapsible?: bool, children: list<array{id: string, path: string, label: string}>}>
     */
    public static function filterSections(string $role, array $sections): array
    {
        $role = self::normalizeRole($role);
        $filtered = [];

        foreach ($sections as $group) {
            $children = [];

            foreach ($group['children'] as $child) {
                if (self::canAccessSection($role, (string) ($child['id'] ?? ''))) {
                    $children[] = $child;
                }
            }

            if ($children === []) {
                continue;
            }

            $group['children'] = $children;
            $filtered[] = $group;
        }

        return $filtered;
    }
}
