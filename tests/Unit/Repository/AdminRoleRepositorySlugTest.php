<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Repository;

use Mt2Cms\Admin\RoleSlugExistsException;
use Mt2Cms\Repository\AdminRoleRepository;
use PHPUnit\Framework\TestCase;

final class AdminRoleRepositorySlugTest extends TestCase
{
    public function testResolveUniqueSlugReturnsBaseWhenFree(): void
    {
        $repo = new FakeAdminRoleRepository(['moderator']);

        self::assertSame('finance', $repo->resolveUniqueSlug('finance'));
    }

    public function testResolveUniqueSlugSuffixesWhenTaken(): void
    {
        $repo = new FakeAdminRoleRepository(['support']);

        self::assertSame('support-2', $repo->resolveUniqueSlug('support'));
    }

    public function testResolveUniqueSlugThrowsWhenNoSuffixAvailable(): void
    {
        $taken = ['moderator'];

        for ($i = 2; $i <= 99; $i++) {
            $taken[] = 'moderator-' . $i;
        }

        $repo = new FakeAdminRoleRepository($taken);

        $this->expectException(RoleSlugExistsException::class);
        $repo->resolveUniqueSlug('moderator');
    }
}

/** @internal */
final class FakeAdminRoleRepository extends AdminRoleRepository
{
    /** @param list<string> $existing */
    public function __construct(private array $existing)
    {
        parent::__construct();
    }

    protected function database(): string
    {
        return 'cms';
    }

    public function slugExists(string $slug): bool
    {
        return in_array($slug, $this->existing, true);
    }
}
