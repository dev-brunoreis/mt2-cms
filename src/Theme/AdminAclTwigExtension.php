<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Service\AclService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminAclTwigExtension extends AbstractExtension
{
    public function __construct(
        private AclService $acl,
        private AdminAuth $adminAuth,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('acl_allowed', function (string $resourceId): bool {
                return $this->acl->isAllowed($this->adminAuth->user(), $resourceId);
            }),
        ];
    }
}
