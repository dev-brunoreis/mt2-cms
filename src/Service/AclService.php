<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminSectionCatalog;
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
    public function canAccess(?array $admin, string $sectionId): bool
    {
        if ($admin === null) {
            return false;
        }

        $role = (string) ($admin['role'] ?? '');

        if (AdminPermissions::isSuper($role)) {
            return true;
        }

        if (AdminSectionCatalog::isSuperOnly($sectionId)) {
            return false;
        }

        $allowed = $this->effectiveSections(
            (int) $admin['id'],
            $role,
            (bool) ($admin['use_custom_acl'] ?? false),
        );

        return in_array($sectionId, $allowed, true);
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
    public function assignableSections(): array
    {
        return AdminSectionCatalog::assignableIds();
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

        return null;
    }

    /**
     * @return list<string>
     */
    public function effectiveSections(int $adminId, string $role, bool $useCustomAcl): array
    {
        if ($useCustomAcl) {
            return $this->adminSections($adminId);
        }

        return $this->roleSections($role);
    }

    /**
     * @return list<string>
     */
    public function roleSections(string $role): array
    {
        if (AdminPermissions::isSuper($role)) {
            return $this->assignableSections();
        }

        if (!isset($this->roleCache[$role])) {
            $this->roleCache[$role] = $this->repository->roleSections($role);
        }

        return $this->roleCache[$role];
    }

    /**
     * @return list<string>
     */
    public function adminSections(int $adminId): array
    {
        if (!isset($this->adminCache[$adminId])) {
            $this->adminCache[$adminId] = $this->repository->adminSections($adminId);
        }

        return $this->adminCache[$adminId];
    }

    /**
     * @param list<string> $sections
     */
    public function saveRoleSections(string $role, array $sections): void
    {
        if (AdminPermissions::isSuper($role)) {
            throw new \InvalidArgumentException('admin.roles.reserved_slug');
        }

        $sections = $this->sanitizeAssignable($sections);
        $this->repository->replaceRoleSections($role, $sections);
        unset($this->roleCache[$role]);
    }

    /**
     * @param list<string> $sections
     */
    public function saveAdminSections(int $adminId, array $sections): void
    {
        $sections = $this->sanitizeAssignable($sections);
        $this->repository->replaceAdminSections($adminId, $sections);
        unset($this->adminCache[$adminId]);
    }

    public function seedDefaults(): void
    {
        $this->roles->seedDefaults();
    }

    /**
     * @param list<string> $sections
     * @return list<string>
     */
    private function sanitizeAssignable(array $sections): array
    {
        $allowed = array_flip($this->assignableSections());
        $clean = [];

        foreach ($sections as $sectionId) {
            if (isset($allowed[$sectionId])) {
                $clean[] = $sectionId;
            }
        }

        sort($clean);

        return array_values(array_unique($clean));
    }
}
