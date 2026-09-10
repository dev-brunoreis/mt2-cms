<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class AclRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function hasRoleSections(): bool
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM acl_role_sections') > 0;
    }

    public function hasRoleResources(): bool
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM acl_role_resources') > 0;
    }

    /**
     * @return list<string>
     */
    public function roleSections(string $role): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT section_id FROM acl_role_sections WHERE role = ? ORDER BY section_id',
            [$role],
        );

        return array_map(static fn (array $row): string => (string) $row['section_id'], $rows);
    }

    /**
     * @return list<string>
     */
    public function roleResources(string $role): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT resource_id FROM acl_role_resources WHERE role = ? ORDER BY resource_id',
            [$role],
        );

        return array_map(static fn (array $row): string => (string) $row['resource_id'], $rows);
    }

    /**
     * @param list<string> $sections
     */
    public function replaceRoleSections(string $role, array $sections): void
    {
        $this->db()->execute('DELETE FROM acl_role_sections WHERE role = ?', [$role]);

        foreach ($sections as $sectionId) {
            $this->db()->execute(
                'INSERT INTO acl_role_sections (role, section_id) VALUES (?, ?)',
                [$role, $sectionId],
            );
        }
    }

    /**
     * @param list<string> $resources
     */
    public function replaceRoleResources(string $role, array $resources): void
    {
        $this->db()->execute('DELETE FROM acl_role_resources WHERE role = ?', [$role]);

        foreach ($resources as $resourceId) {
            $this->db()->execute(
                'INSERT INTO acl_role_resources (role, resource_id) VALUES (?, ?)',
                [$role, $resourceId],
            );
        }
    }

    /**
     * @return list<string>
     */
    public function adminSections(int $adminId): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT section_id FROM acl_admin_sections WHERE admin_id = ? ORDER BY section_id',
            [$adminId],
        );

        return array_map(static fn (array $row): string => (string) $row['section_id'], $rows);
    }

    /**
     * @return list<string>
     */
    public function adminResources(int $adminId): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT resource_id FROM acl_admin_resources WHERE admin_id = ? ORDER BY resource_id',
            [$adminId],
        );

        return array_map(static fn (array $row): string => (string) $row['resource_id'], $rows);
    }

    /**
     * @param list<string> $sections
     */
    public function replaceAdminSections(int $adminId, array $sections): void
    {
        $this->db()->execute('DELETE FROM acl_admin_sections WHERE admin_id = ?', [$adminId]);

        foreach ($sections as $sectionId) {
            $this->db()->execute(
                'INSERT INTO acl_admin_sections (admin_id, section_id) VALUES (?, ?)',
                [$adminId, $sectionId],
            );
        }
    }

    /**
     * @param list<string> $resources
     */
    public function replaceAdminResources(int $adminId, array $resources): void
    {
        $this->db()->execute('DELETE FROM acl_admin_resources WHERE admin_id = ?', [$adminId]);

        foreach ($resources as $resourceId) {
            $this->db()->execute(
                'INSERT INTO acl_admin_resources (admin_id, resource_id) VALUES (?, ?)',
                [$adminId, $resourceId],
            );
        }
    }

    /**
     * @param list<string> $sections
     */
    public function seedRoleSections(string $role, array $sections): void
    {
        foreach ($sections as $sectionId) {
            $this->db()->execute(
                'INSERT IGNORE INTO acl_role_sections (role, section_id) VALUES (?, ?)',
                [$role, $sectionId],
            );
        }
    }

    /**
     * @param list<string> $resources
     */
    public function seedRoleResources(string $role, array $resources): void
    {
        foreach ($resources as $resourceId) {
            $this->db()->execute(
                'INSERT IGNORE INTO acl_role_resources (role, resource_id) VALUES (?, ?)',
                [$role, $resourceId],
            );
        }
    }
}
