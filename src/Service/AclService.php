<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminResourceCatalog;
use Mt2Cms\Admin\AdminSections;
use Mt2Cms\Repository\AclRepository;
use Mt2Cms\Repository\AdminRoleRepository;

class AclService
{
    /** @var array<string, list<string>> */
    private array $roleCache = [];

    /** @var array<int, list<string>> */
    private array $adminCache = [];

    public function __construct(
        private AclRepository $repository,
        private AdminRoleRepository $roles,
    ) {
    }

    /**
     * @param array<string, mixed>|null $admin
     */
    public function isAllowed(?array $admin, string $resourceId): bool
    {
        if ($admin === null) {
            return false;
        }

        $role = (string) ($admin['role'] ?? '');

        if (AdminPermissions::isSuper($role)) {
            return true;
        }

        if (AdminResourceCatalog::isSuperOnly($resourceId)) {
            return false;
        }

        $allowed = $this->effectiveResources(
            (int) $admin['id'],
            $role,
            (bool) ($admin['use_custom_acl'] ?? false),
        );

        return AdminResourceCatalog::isAllowedResource($resourceId, $allowed);
    }

    /**
     * @param array<string, mixed>|null $admin
     */
    public function canAccess(?array $admin, string $sectionId): bool
    {
        if ($admin === null) {
            return false;
        }

        $role = (string) ($admin['role'] ?? '');

        if (AdminPermissions::isSuper($role)) {
            return true;
        }

        if (AdminSections::isSuperOnly($sectionId)) {
            return false;
        }

        $allowed = $this->effectiveResources(
            (int) $admin['id'],
            $role,
            (bool) ($admin['use_custom_acl'] ?? false),
        );

        return AdminResourceCatalog::hasAnyResourceForSection($sectionId, $allowed);
    }

    /**
     * @param array<string, mixed>|null $admin
     * @param list<array{id: string, label: string, collapsible?: bool, children: list<array{id: string, path: string, label: string}>}> $sections
     * @return list<array{id: string, label: string, collapsible?: bool, children: list<array{id: string, path: string, label: string}>}>
     */
    public function filterSections(?array $admin, array $sections): array
    {
        if ($admin === null) {
            return $sections;
        }

        $filtered = [];

        foreach ($sections as $group) {
            $children = [];

            foreach ($group['children'] as $child) {
                if ($this->canAccess($admin, (string) ($child['id'] ?? ''))) {
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

    /**
     * @return list<string>
     */
    public function assignableResources(): array
    {
        return AdminResourceCatalog::assignableIds();
    }

    /**
     * @return list<string>
     */
    public function assignableSections(): array
    {
        return AdminSections::assignableIds();
    }

    /**
     * @param array<string, mixed>|null $admin
     */
    public function firstAccessiblePath(?array $admin): ?string
    {
        if ($admin === null) {
            return null;
        }

        foreach (AdminSections::navSectionIds() as $sectionId) {
            if (!$this->canAccess($admin, $sectionId)) {
                continue;
            }

            $path = AdminSections::sectionPath($sectionId);

            if ($path !== null && $path !== '') {
                return $path;
            }
        }

        foreach (AdminResourceCatalog::navHiddenSectionIds() as $sectionId) {
            if (!$this->canAccess($admin, $sectionId)) {
                continue;
            }

            $path = AdminSections::sectionPath($sectionId);

            if ($path !== null && $path !== '') {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function effectiveResources(int $adminId, string $role, bool $useCustomAcl): array
    {
        if ($useCustomAcl) {
            return $this->adminResources($adminId);
        }

        return $this->roleResources($role);
    }

    /**
     * @return list<string>
     */
    public function roleResources(string $role): array
    {
        if (AdminPermissions::isSuper($role)) {
            return $this->assignableResources();
        }

        if (!isset($this->roleCache[$role])) {
            $this->roleCache[$role] = $this->repository->roleResources($role);
        }

        return $this->roleCache[$role];
    }

    /**
     * @return list<string>
     */
    public function adminResources(int $adminId): array
    {
        if (!isset($this->adminCache[$adminId])) {
            $this->adminCache[$adminId] = $this->repository->adminResources($adminId);
        }

        return $this->adminCache[$adminId];
    }

    /**
     * @return list<string>
     */
    public function roleSections(string $role): array
    {
        return $this->roleResources($role);
    }

    /**
     * @return list<string>
     */
    public function adminSections(int $adminId): array
    {
        return $this->adminResources($adminId);
    }

    /**
     * @param list<string> $resources
     */
    public function saveRoleResources(string $role, array $resources): void
    {
        if (AdminPermissions::isSuper($role)) {
            throw new \InvalidArgumentException('admin.roles.reserved_slug');
        }

        $resources = $this->sanitizeAssignableResources($resources);
        $this->repository->replaceRoleResources($role, $resources);
        unset($this->roleCache[$role]);
    }

    /**
     * @param list<string> $resources
     */
    public function saveAdminResources(int $adminId, array $resources): void
    {
        $resources = $this->sanitizeAssignableResources($resources);
        $this->repository->replaceAdminResources($adminId, $resources);
        unset($this->adminCache[$adminId]);
    }

    /**
     * @param list<string> $sections
     */
    public function saveRoleSections(string $role, array $sections): void
    {
        $this->saveRoleResources($role, $this->expandSectionsToResources($sections));
    }

    /**
     * @param list<string> $sections
     */
    public function saveAdminSections(int $adminId, array $sections): void
    {
        $this->saveAdminResources($adminId, $this->expandSectionsToResources($sections));
    }

    public function seedDefaults(): void
    {
        $this->roles->seedDefaults();
    }

    /**
     * @param list<string> $resources
     * @return list<string>
     */
    private function sanitizeAssignableResources(array $resources): array
    {
        $allowed = array_flip($this->assignableResources());
        $clean = [];

        foreach ($resources as $resourceId) {
            if (isset($allowed[$resourceId])) {
                $clean[] = $resourceId;
            }
        }

        sort($clean);

        return array_values(array_unique($clean));
    }

    /**
     * @param list<string> $sections
     * @return list<string>
     */
    private function expandSectionsToResources(array $sections): array
    {
        $resources = [];

        foreach ($sections as $sectionId) {
            foreach (AdminResourceCatalog::resourcesForLegacySection($sectionId) as $resourceId) {
                $resources[] = $resourceId;
            }
        }

        return $this->sanitizeAssignableResources($resources);
    }
}
