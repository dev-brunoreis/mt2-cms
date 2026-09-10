<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Repository\AdminAuditRepository;

class AdminAuditService
{
    public function __construct(
        private AdminAuditRepository $repository,
        private AdminAuth $adminAuth,
    ) {
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?array $meta = null,
    ): void {
        $adminId = $this->adminAuth->id();

        if ($adminId === null) {
            return;
        }

        $login = $this->adminAuth->login() ?? '';

        try {
            $this->repository->insert(
                $adminId,
                $login,
                $action,
                $targetType,
                $targetId,
                $this->sanitizeMeta($meta),
                $this->clientIp(),
            );
        } catch (\Throwable) {
            // Audit must not break admin actions.
        }
    }

    /**
     * @param array<string, mixed>|null $meta
     * @return array<string, mixed>|null
     */
    private function sanitizeMeta(?array $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        $blocked = ['password', 'pin', 'hash', 'social_id', 'securitycode', '_csrf'];
        $clean = [];

        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);

            if (in_array($lower, $blocked, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
