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
}
