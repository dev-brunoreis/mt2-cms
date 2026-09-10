<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

class AdminAuditRepository extends Repository
{
    protected function database(): string
    {
        return 'cms';
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function insert(
        int $adminId,
        string $login,
        string $action,
        string $targetType,
        ?int $targetId,
        ?array $meta,
        string $ip,
    ): void {
        $metaJson = null;

        if ($meta !== null && $meta !== []) {
            $metaJson = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $this->db()->execute(
            'INSERT INTO admin_audit_log
                (admin_id, login, action, target_type, target_id, meta, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$adminId, $login, $action, $targetType, $targetId, $metaJson, $ip],
        );
    }
}
