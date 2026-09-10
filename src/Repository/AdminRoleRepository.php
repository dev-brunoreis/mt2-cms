<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminSectionCatalog;
use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;
use Mt2Cms\Admin\RoleSlug;
use Mt2Cms\Admin\RoleSlugExistsException;

class AdminRoleRepository extends Repository implements ProvidesAdminGrid
{
    protected function database(): string
    {
        return 'cms';
    }

    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/roles', 'admin.roles')
            ->defaultSort('label', 'asc')
            ->idField('slug')
            ->massIdType('string')
            ->massActions('/admin/roles/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.roles.confirm_mass_delete'],
            ])
            ->orderBy([
                'slug' => 'r.slug',
                'label' => 'r.label',
                'admin_count' => 'admin_count',
                'section_count' => 'section_count',
                'created_at' => 'r.created_at',
            ])
            ->columns([
                ['key' => 'label', 'label' => 'admin.roles.label', 'sort' => 'label', 'type' => 'link', 'href' => '/admin/roles/{id}'],
                ['key' => 'slug', 'label' => 'admin.roles.slug', 'sort' => 'slug', 'type' => 'muted'],
                ['key' => 'admin_count', 'label' => 'admin.roles.admin_count', 'sort' => 'admin_count', 'type' => 'number'],
                ['key' => 'section_count', 'label' => 'admin.roles.section_count', 'sort' => 'section_count', 'type' => 'number'],
                ['key' => 'created_at', 'label' => 'admin.roles.created_at', 'sort' => 'created_at', 'type' => 'date'],
            ]);
    }

    public function hasAny(): bool
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM admin_roles') > 0;
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    public function listForSelect(): array
    {
        return $this->db()->fetchAll(
            'SELECT slug, label FROM admin_roles ORDER BY label ASC',
        );
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = RoleSlug::assertValid($slug);

        return $this->db()->fetch(
            'SELECT id, slug, label, created_at FROM admin_roles WHERE slug = ? LIMIT 1',
            [$slug],
        );
    }

    public function slugExists(string $slug): bool
    {
        if (\Mt2Cms\Admin\AdminPermissions::isSuper($slug)) {
            return false;
        }

        try {
            $slug = RoleSlug::assertValid($slug);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $this->db()->fetchColumn(
            'SELECT 1 FROM admin_roles WHERE slug = ? LIMIT 1',
            [$slug],
        ) !== null;
    }

    public function countAdminsBySlug(string $slug): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM admins WHERE role = ?',
            [$slug],
        );
    }

    public function countSuperAdmins(): int
    {
        return $this->countAdminsBySlug(AdminPermissions::ROLE_SUPER);
    }

    /**
     * @return array{slug: string, label: string, admin_count: int, section_count: null, created_at: null, is_system: true}
     */
    public function superGridRow(): array
    {
        return [
            'slug' => AdminPermissions::ROLE_SUPER,
            'label' => 'Super',
            'admin_count' => $this->countSuperAdmins(),
            'section_count' => null,
            'created_at' => null,
            'is_system' => true,
            'can_mass' => false,
        ];
    }

    public function superMatchesSearch(GridQuery $query): bool
    {
        if ($query->q === null || $query->q === '') {
            return true;
        }

        $needle = strtolower($query->q);

        return str_contains('super', $needle) || str_contains($needle, 'super');
    }

    /**
     * @param array<string, mixed> $grid
     * @return array<string, mixed>
     */
    public function augmentGridWithSuper(array $grid, GridQuery $query): array
    {
        if (!$this->superMatchesSearch($query)) {
            return $grid;
        }

        $grid['total'] = (int) $grid['total'] + 1;

        if ($query->page !== 1) {
            return $grid;
        }

        $rows = $grid['rows'];
        array_unshift($rows, $this->superGridRow());

        if (count($rows) > $query->perPage) {
            array_pop($rows);
        }

        $grid['rows'] = $rows;

        return $grid;
    }

    /**
     * @param list<string> $sections
     */
    public function create(string $label, ?string $slug, array $sections): string
    {
        $label = trim($label);

        if ($label === '' || strlen($label) > 64) {
            throw new \InvalidArgumentException('admin.roles.invalid_label');
        }

        $slug = $slug !== null && $slug !== ''
            ? RoleSlug::assertValid($slug)
            : RoleSlug::fromLabel($label);
        $slug = $this->resolveUniqueSlug($slug);

        $db = $this->db();
        $db->beginTransaction();

        try {
            $db->execute(
                'INSERT INTO admin_roles (slug, label) VALUES (?, ?)',
                [$slug, $label],
            );
            (new AclRepository($db))->replaceRoleSections($slug, $this->sanitizeSections($sections));
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        }

        return $slug;
    }

    public function resolveUniqueSlug(string $slug): string
    {
        $slug = RoleSlug::assertValid($slug);

        if (!$this->slugExists($slug)) {
            return $slug;
        }

        $base = $slug;

        for ($i = 2; $i <= 99; $i++) {
            $suffix = '-' . $i;
            $maxBase = 32 - strlen($suffix);
            $candidate = ($maxBase < 1 ? '' : rtrim(substr($base, 0, $maxBase), '-')) . $suffix;
            $candidate = RoleSlug::assertValid($candidate);

            if (!$this->slugExists($candidate)) {
                return $candidate;
            }
        }

        throw new RoleSlugExistsException($slug);
    }

    /**
     * @param list<string> $sections
     */
    public function update(string $slug, string $label, array $sections): void
    {
        $slug = RoleSlug::assertValid($slug);
        $label = trim($label);

        if ($label === '' || strlen($label) > 64) {
            throw new \InvalidArgumentException('admin.roles.invalid_label');
        }

        if ($this->findBySlug($slug) === null) {
            throw new \RuntimeException('admin.roles.not_found');
        }

        $this->db()->execute(
            'UPDATE admin_roles SET label = ? WHERE slug = ?',
            [$label, $slug],
        );

        (new AclRepository($this->db()))->replaceRoleSections($slug, $this->sanitizeSections($sections));
    }

    public function delete(string $slug): bool
    {
        $slug = RoleSlug::assertValid($slug);

        if ($this->findBySlug($slug) === null) {
            return false;
        }

        if ($this->countAdminsBySlug($slug) > 0) {
            throw new \RuntimeException('admin.roles.in_use');
        }

        $this->db()->execute('DELETE FROM acl_role_sections WHERE role = ?', [$slug]);

        return $this->db()->execute('DELETE FROM admin_roles WHERE slug = ?', [$slug]) > 0;
    }

    public function seedDefaults(): void
    {
        if ($this->defaultsAlreadySeeded()) {
            return;
        }

        if ($this->hasAny()) {
            $this->markDefaultsSeeded();

            return;
        }

        $acl = new AclRepository($this->db());
        $defaults = [
            ['slug' => 'support', 'label' => 'Support', 'sections' => AdminSectionCatalog::defaultSupportSections()],
            ['slug' => 'content', 'label' => 'Content', 'sections' => AdminSectionCatalog::defaultContentSections()],
        ];

        foreach ($defaults as $row) {
            $this->db()->execute(
                'INSERT INTO admin_roles (slug, label) VALUES (?, ?)',
                [$row['slug'], $row['label']],
            );
            $acl->seedRoleSections($row['slug'], $row['sections']);
        }

        $this->markDefaultsSeeded();
    }

    private function defaultsAlreadySeeded(): bool
    {
        return $this->db()->fetchColumn(
            'SELECT 1 FROM settings WHERE setting_key = ? LIMIT 1',
            ['admin_roles_defaults_seeded'],
        ) !== null;
    }

    private function markDefaultsSeeded(): void
    {
        $this->db()->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['admin_roles_defaults_seeded', '1'],
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM admin_roles r' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'r.label ASC');

        $rows = $this->db()->fetchAll(
            'SELECT r.slug, r.label, r.created_at,
                    (SELECT COUNT(*) FROM admins a WHERE a.role = r.slug) AS admin_count,
                    (SELECT COUNT(*) FROM acl_role_sections s WHERE s.role = r.slug) AS section_count
             FROM admin_roles r' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        foreach ($rows as &$row) {
            $row['can_mass'] = (int) ($row['admin_count'] ?? 0) === 0;
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function labelMap(): array
    {
        $map = ['super' => 'Super'];
        $rows = $this->db()->fetchAll('SELECT slug, label FROM admin_roles');

        foreach ($rows as $row) {
            $map[(string) $row['slug']] = (string) $row['label'];
        }

        return $map;
    }

    /**
     * @param list<string> $sections
     * @return list<string>
     */
    private function sanitizeSections(array $sections): array
    {
        $allowed = array_flip(AdminSectionCatalog::assignableIds());
        $clean = [];

        foreach ($sections as $sectionId) {
            if (isset($allowed[$sectionId])) {
                $clean[] = $sectionId;
            }
        }

        sort($clean);

        return array_values(array_unique($clean));
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $where = ' WHERE 1=1';
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
            $where .= ' AND (r.label LIKE ? OR r.slug LIKE ?)';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }

        return [$where, $params];
    }
}
