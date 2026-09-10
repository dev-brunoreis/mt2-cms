<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\AdminPermissions;
use Mt2Cms\Model\Database;
use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class AdminRepository extends Repository implements ProvidesAdminGrid
{
    public function __construct(
        Database $db = new Database(),
        private ?AdminRoleRepository $roles = null,
    ) {
        parent::__construct($db);
    }

    protected function database(): string
    {
        return 'cms';
    }

    /** @return list<string> */
    protected function hiddenColumns(): array
    {
        return ['password', 'totp_secret'];
    }

    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/system/admins', 'admin.admins')
            ->defaultSort('login', 'asc')
            ->orderBy([
                'id' => 'id',
                'login' => 'login',
                'role' => 'role',
                'created_at' => 'created_at',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.admins.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'login', 'label' => 'admin.admins.login', 'sort' => 'login', 'type' => 'link', 'href' => '/admin/system/admins/{id}'],
                ['key' => 'role_label', 'label' => 'admin.admins.role', 'sort' => 'role', 'type' => 'text'],
                ['key' => 'use_custom_acl', 'label' => 'admin.admins.custom_acl', 'type' => 'bool'],
                ['key' => 'created_at', 'label' => 'admin.admins.created_at', 'sort' => 'created_at', 'type' => 'date'],
            ])
            ->massActions('/admin/system/admins/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.admins.confirm_mass_delete'],
            ])
            ->filters([
                ['key' => 'role', 'label' => 'admin.admins.role', 'type' => 'select', 'options' => [], 'translateOptions' => false],
            ]);
    }

    public function create(string $login, string $password, string $role = AdminPermissions::ROLE_SUPER): int
    {
        $login = trim($login);
        $role = strtolower(trim($role));

        if ($login === '' || strlen($login) > 64) {
            throw new \InvalidArgumentException('admin.invalid_login');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('admin.invalid_password');
        }

        if ($this->findByLogin($login) !== null) {
            throw new \RuntimeException('admin.login_exists');
        }

        $this->assertAssignableRole($role);

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->db()->execute(
            'INSERT INTO admins (login, password, role, use_custom_acl) VALUES (?, ?, ?, 0)',
            [$login, $hash, $role],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function findByLogin(string $login): ?array
    {
        return $this->reveal($this->db()->fetch(
            'SELECT id, login, password, role, use_custom_acl, created_at FROM admins WHERE login = ? LIMIT 1',
            [$login],
        ));
    }

    public function findById(int $id): ?array
    {
        return $this->reveal($this->db()->fetch(
            'SELECT id, login, role, use_custom_acl, totp_enabled, created_at FROM admins WHERE id = ? LIMIT 1',
            [$id],
        ));
    }

    /**
     * @return array{id: int, login: string, totp_enabled: bool}|null
     */
    public function authenticate(string $login, string $password): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, login, password, totp_enabled FROM admins WHERE login = ? LIMIT 1',
            [trim($login)],
        );

        if ($row === null) {
            return null;
        }

        if (!password_verify($password, (string) $row['password'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'login' => (string) $row['login'],
            'totp_enabled' => (int) ($row['totp_enabled'] ?? 0) === 1,
        ];
    }

    public function count(): int
    {
        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM admins');
    }

    public function countSupers(): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM admins WHERE role = ?',
            [AdminPermissions::ROLE_SUPER],
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn('SELECT COUNT(*) FROM admins' . $where, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'id DESC');
        $labels = $this->roleRepository()->labelMap();

        $rows = $this->db()->fetchAll(
            'SELECT id, login, role, use_custom_acl, created_at FROM admins' . $where . $order . '
             LIMIT ? OFFSET ?',
            $params,
        );

        foreach ($rows as &$row) {
            $role = (string) ($row['role'] ?? '');
            $row['use_custom_acl'] = (string) (int) ($row['use_custom_acl'] ?? 0);
            $row['role_label'] = $labels[$role] ?? $role;
            $row['can_mass'] = !AdminPermissions::isSuper($role);
        }

        unset($row);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function roleFilterOptions(): array
    {
        $options = ['super' => 'Super'];

        foreach ($this->roleRepository()->listForSelect() as $role) {
            $options[(string) $role['slug']] = (string) $role['label'];
        }

        return $options;
    }

    public function update(int $id, string $login, string $role, bool $useCustomAcl, ?string $password = null): void
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('admin.admins.not_found');
        }
        $login = trim($login);
        $role = strtolower(trim($role));

        if ($login === '' || strlen($login) > 64) {
            throw new \InvalidArgumentException('admin.invalid_login');
        }

        $this->assertAssignableRole($role);

        $existing = $this->findById($id);

        if ($existing === null) {
            throw new \RuntimeException('admin.admins.not_found');
        }

        if (AdminPermissions::isSuper((string) ($existing['role'] ?? ''))) {
            throw new \InvalidArgumentException('admin.admins.cannot_edit_super');
        }

        if (AdminPermissions::isSuper($role)) {
            $useCustomAcl = false;
        }

        $duplicate = $this->db()->fetch(
            'SELECT id FROM admins WHERE login = ? AND id <> ? LIMIT 1',
            [$login, $id],
        );

        if ($duplicate !== null) {
            throw new \RuntimeException('admin.login_exists');
        }

        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('admin.invalid_password');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->db()->execute(
                'UPDATE admins SET login = ?, role = ?, use_custom_acl = ?, password = ? WHERE id = ?',
                [$login, $role, $useCustomAcl ? 1 : 0, $hash, $id],
            );
        } else {
            $this->db()->execute(
                'UPDATE admins SET login = ?, role = ?, use_custom_acl = ? WHERE id = ?',
                [$login, $role, $useCustomAcl ? 1 : 0, $id],
            );
        }

        if (AdminPermissions::isSuper($role)) {
            (new AclRepository($this->db))->replaceAdminResources($id, []);
        }
    }

    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $row = $this->findById($id);

        if ($row === null) {
            return false;
        }

        if (AdminPermissions::isSuper((string) ($row['role'] ?? ''))) {
            if ($this->countSupers() <= 1) {
                throw new \RuntimeException('admin.admins.cannot_delete_last_super');
            }

            throw new \RuntimeException('admin.admins.cannot_delete_super');
        }

        return $this->db()->execute('DELETE FROM admins WHERE id = ?', [$id]) > 0;
    }

    private function assertAssignableRole(string $role): void
    {
        if (AdminPermissions::isSuper($role)) {
            return;
        }

        if (!$this->roleRepository()->slugExists($role)) {
            throw new \InvalidArgumentException('admin.admins.invalid_role');
        }
    }

    private function roleRepository(): AdminRoleRepository
    {
        return $this->roles ?? new AdminRoleRepository($this->db);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $where = ' WHERE 1=1';
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
            $where .= ' AND login LIKE ?';
            $params[] = '%' . $escaped . '%';
        }

        foreach ($query->filters as $key => $value) {
            if ($key === 'role' && $value !== '') {
                $where .= ' AND role = ?';
                $params[] = $value;
            }
        }

        return [$where, $params];
    }
}
