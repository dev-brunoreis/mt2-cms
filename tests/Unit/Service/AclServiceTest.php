<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Admin\AdminResourceCatalog;
use Mt2Cms\Repository\AclRepository;
use Mt2Cms\Repository\AdminRoleRepository;
use Mt2Cms\Service\AclService;
use PHPUnit\Framework\TestCase;

final class AclServiceTest extends TestCase
{
    public function testSuperBypassesEverything(): void
    {
        $service = $this->service(new FakeAclRepository());

        self::assertTrue($service->canAccess([
            'id' => 1,
            'role' => AdminPermissions::ROLE_SUPER,
            'use_custom_acl' => false,
        ], 'admins'));
        self::assertTrue($service->isAllowed([
            'id' => 1,
            'role' => AdminPermissions::ROLE_SUPER,
            'use_custom_acl' => false,
        ], 'game/accounts/delete'));
    }

    public function testSupportUsesRoleResourcesForSectionAccess(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleResources = [
            'support' => ['game/accounts/view', 'content/tickets/view'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 2,
            'role' => 'support',
            'use_custom_acl' => false,
        ];

        self::assertTrue($service->canAccess($admin, 'accounts'));
        self::assertFalse($service->canAccess($admin, 'news'));
        self::assertTrue($service->isAllowed($admin, 'game/accounts/view'));
        self::assertFalse($service->isAllowed($admin, 'game/accounts/create'));
    }

    public function testResourceInheritanceViaParentGrant(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleResources = [
            'editor' => ['content/news/posts'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 6,
            'role' => 'editor',
            'use_custom_acl' => false,
        ];

        self::assertTrue($service->isAllowed($admin, 'content/news/posts/delete'));
        self::assertFalse($service->isAllowed($admin, 'content/news/comments/view'));
    }

    public function testCustomAdminOverridesRoleTemplate(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleResources = [
            'content' => ['content/news/posts/view'],
        ];
        $repo->adminResources = [
            3 => ['content/tickets/view'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 3,
            'role' => 'content',
            'use_custom_acl' => true,
        ];

        self::assertTrue($service->canAccess($admin, 'tickets'));
        self::assertFalse($service->canAccess($admin, 'news'));
    }

    public function testFirstAccessiblePathIncludesNavHiddenGameData(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleResources = [
            'proto' => ['game-data/shops/view'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 7,
            'role' => 'proto',
            'use_custom_acl' => false,
        ];

        self::assertSame('/admin/game-data/shops', $service->firstAccessiblePath($admin));
    }

    public function testSaveRoleResourcesRejectsSuperSlug(): void
    {
        $service = $this->service(new FakeAclRepository());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.roles.reserved_slug');

        $service->saveRoleResources('super', ['game/accounts/view']);
    }

    public function testExpandSectionsToResourcesOnSaveRoleSections(): void
    {
        $repo = new FakeAclRepository();
        $service = $this->service($repo);

        $service->saveRoleSections('support', ['news']);

        self::assertSame(
            AdminResourceCatalog::resourcesForLegacySection('news'),
            $repo->lastRoleResources['support'] ?? [],
        );
    }

    private function service(FakeAclRepository $acl): AclService
    {
        return new AclService($acl, new FakeAdminRoleRepository());
    }
}

/** @internal */
final class FakeAclRepository extends AclRepository
{
    /** @var array<string, list<string>> */
    public array $roleResources = [];

    /** @var array<int, list<string>> */
    public array $adminResources = [];

    /** @var array<string, list<string>> */
    public array $lastRoleResources = [];

    protected function database(): string
    {
        return 'cms';
    }

    public function hasRoleResources(): bool
    {
        return $this->roleResources !== [];
    }

    public function roleResources(string $role): array
    {
        return $this->roleResources[$role] ?? [];
    }

    public function adminResources(int $adminId): array
    {
        return $this->adminResources[$adminId] ?? [];
    }

    public function replaceRoleResources(string $role, array $resources): void
    {
        $this->lastRoleResources[$role] = $resources;
        $this->roleResources[$role] = $resources;
    }

    public function replaceAdminResources(int $adminId, array $resources): void
    {
        $this->adminResources[$adminId] = $resources;
    }
}

/** @internal */
final class FakeAdminRoleRepository extends AdminRoleRepository
{
    protected function database(): string
    {
        return 'cms';
    }

    public function seedDefaults(): void
    {
    }
}
