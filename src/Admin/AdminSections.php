<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class AdminSections
{
    /**
     * @return list<array{id: string, label: string, children: list<array{id: string, path: string, label: string}>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'game',
                'label' => 'admin.nav.game',
                'children' => [
                    [
                        'id' => 'accounts',
                        'path' => '/admin/accounts',
                        'label' => 'admin.nav.accounts',
                    ],
                    [
                        'id' => 'characters',
                        'path' => '/admin/characters',
                        'label' => 'admin.nav.characters',
                    ],
                ],
            ],
            [
                'id' => 'configuration',
                'label' => 'admin.nav.configuration',
                'children' => [
                    [
                        'id' => 'registration',
                        'path' => '/admin/registration',
                        'label' => 'admin.nav.registration',
                    ],
                    [
                        'id' => 'themes',
                        'path' => '/admin/themes',
                        'label' => 'admin.nav.themes',
                    ],
                    [
                        'id' => 'locale',
                        'path' => '/admin/locale',
                        'label' => 'admin.nav.locale',
                    ],
                ],
            ],
        ];
    }

    public static function firstPath(): string
    {
        foreach (self::all() as $group) {
            $first = $group['children'][0]['path'] ?? null;

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return '/admin/registration';
    }

    public static function groupForSection(string $sectionId): ?string
    {
        foreach (self::all() as $group) {
            foreach ($group['children'] as $child) {
                if ($child['id'] === $sectionId) {
                    return $group['id'];
                }
            }
        }

        return null;
    }
}
