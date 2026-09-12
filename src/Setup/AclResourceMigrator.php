<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Admin\AdminResourceCatalog;
use Mt2Cms\Support\Database;
use Mt2Cms\Repository\AclRepository;

/**
 * Expands legacy acl_*_sections rows into acl_*_resources (run once after migration 006).
 */
final class AclResourceMigrator
{
    public function __construct(private Database $db)
    {
    }

    public function migrateIfNeeded(): void
    {
        $acl = new AclRepository($this->db);

        if ($acl->hasRoleResources()) {
            return;
        }

        if (!$acl->hasRoleSections()) {
            return;
        }

        $this->db->useDatabase('cms');
        $this->db->beginTransaction();

        try {
            foreach ($this->db->fetchAll('SELECT DISTINCT role FROM acl_role_sections ORDER BY role') as $row) {
                $role = (string) $row['role'];
                $sections = $acl->roleSections($role);
                $acl->replaceRoleResources($role, $this->expandSections($sections));
            }

            foreach ($this->db->fetchAll('SELECT DISTINCT admin_id FROM acl_admin_sections ORDER BY admin_id') as $row) {
                $adminId = (int) $row['admin_id'];
                $sections = $acl->adminSections($adminId);
                $acl->replaceAdminResources($adminId, $this->expandSections($sections));
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    /**
     * @param list<string> $sections
     * @return list<string>
     */
    private function expandSections(array $sections): array
    {
        $resources = [];

        foreach ($sections as $sectionId) {
            foreach (AdminResourceCatalog::resourcesForLegacySection($sectionId) as $resourceId) {
                $resources[] = $resourceId;
            }
        }

        sort($resources);

        return array_values(array_unique($resources));
    }
}
