<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class AdminSections
{
    /**
     * @return list<array{id: string, path: string, label: string}>
     */
    public static function all(): array
    {
        return [
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
        ];
    }

    public static function firstPath(): string
    {
        $sections = self::all();

        return $sections[0]['path'] ?? '/admin/registration';
    }
}
