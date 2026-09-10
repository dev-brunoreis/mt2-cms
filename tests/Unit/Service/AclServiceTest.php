<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Admin\AdminPermissions;
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
    }

    public function testSupportUsesRoleTemplate(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleSections = [
            'support' => ['accounts', 'tickets'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 2,
            'role' => 'support',
            'use_custom_acl' => false,
        ];

        self::assertTrue($service->canAccess($admin, 'accounts'));
        self::assertFalse($service->canAccess($admin, 'news'));
        self::assertFalse($service->canAccess($admin, 'admins'));
    }

    public function testArbitraryRoleSlugUsesRoleTemplate(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleSections = [
            'moderator' => ['tickets', 'news'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 4,
            'role' => 'moderator',
            'use_custom_acl' => false,
        ];

        self::assertTrue($service->canAccess($admin, 'tickets'));
        self::assertTrue($service->canAccess($admin, 'news'));
        self::assertFalse($service->canAccess($admin, 'admins'));
    }

    public function testUnknownRoleHasNoAccess(): void
    {
        $service = $this->service(new FakeAclRepository());

        $admin = [
            'id' => 5,
            'role' => 'unknown-role',
            'use_custom_acl' => false,
        ];

        self::assertFalse($service->canAccess($admin, 'accounts'));
    }

    public function testCustomAdminOverridesRoleTemplate(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleSections = [
            'content' => ['news'],
        ];
        $repo->adminSections = [
            3 => ['tickets'],
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

    public function testFirstAccessiblePathSkipsDeniedSections(): void
    {
        $repo = new FakeAclRepository();
        $repo->roleSections = [
            'editor' => ['news'],
        ];
        $service = $this->service($repo);

        $admin = [
            'id' => 6,
            'role' => 'editor',
            'use_custom_acl' => false,
        ];

        self::assertSame('/admin/content/news', $service->firstAccessiblePath($admin));
    }

    public function testSaveRoleSectionsRejectsSuperSlug(): void
    {
        $service = $this->service(new FakeAclRepository());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('admin.roles.reserved_slug');

        $service->saveRoleSections('super', ['accounts']);
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
    public array $roleSections = [];

    /** @var array<int, list<string>> */
    public array $adminSections = [];

    protected function database(): string
    {
        return 'cms';
    }

    public function hasRoleSections(): bool
    {
        return $this->roleSections !== [];
    }

    public function roleSections(string $role): array
    {
        return $this->roleSections[$role] ?? [];
    }

    public function adminSections(int $adminId): array
    {
        return $this->adminSections[$adminId] ?? [];
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
