<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class AccountRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/game/accounts', 'admin.accounts')
            ->orderBy([
                'id' => 'id',
                'login' => 'login',
                'email' => 'email',
                'status' => 'status',
                'cash' => 'cash',
                'mileage' => 'mileage',
                'last_play' => 'last_play',
            ])
            ->columns([
                ['key' => 'id', 'label' => 'admin.accounts.id', 'sort' => 'id', 'type' => 'muted'],
                ['key' => 'login', 'label' => 'admin.accounts.login', 'sort' => 'login', 'type' => 'link', 'href' => '/admin/game/accounts/{id}'],
                ['key' => 'email', 'label' => 'admin.accounts.email', 'sort' => 'email', 'type' => 'text'],
                ['key' => 'status', 'label' => 'admin.accounts.status', 'sort' => 'status', 'type' => 'badge', 'badgeMap' => [
                    'OK' => ['class' => 'admin-badge-ok', 'label' => 'admin.accounts.status_ok'],
                    'BLOCK' => ['class' => 'admin-badge-danger', 'label' => 'admin.accounts.status_block'],
                ]],
                ['key' => 'empire', 'label' => 'admin.accounts.empire', 'type' => 'empire'],
                ['key' => 'cash', 'label' => 'admin.accounts.cash', 'sort' => 'cash', 'type' => 'number'],
                ['key' => 'mileage', 'label' => 'admin.accounts.mileage', 'sort' => 'mileage', 'type' => 'number'],
                ['key' => 'last_play', 'label' => 'admin.accounts.last_play', 'sort' => 'last_play', 'type' => 'date'],
                ['key' => 'ip', 'label' => 'admin.accounts.last_ip', 'type' => 'text'],
            ])
            ->filters([
                ['key' => 'status', 'label' => 'admin.accounts.status', 'type' => 'select', 'options' => [
                    'OK' => 'admin.accounts.status_ok',
                    'BLOCK' => 'admin.accounts.status_block',
                ]],
            ])
            ->massActions('/admin/game/accounts/mass', [
                ['id' => 'block', 'label' => 'admin.grid.block', 'confirm' => 'admin.accounts.confirm_mass_block'],
                ['id' => 'unblock', 'label' => 'admin.grid.unblock', 'confirm' => 'admin.accounts.confirm_mass_unblock'],
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.accounts.confirm_mass_delete'],
            ]);
    }

    protected function database(): string
    {
        return 'account';
    }

    public function findById(int $id): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account` WHERE id = ?',
                [$id],
            ),
        );
    }

    public function findByLogin(string $login): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account` WHERE login = ?',
                [$login],
            ),
        );
    }

    /**
     * Verify credentials. Returns a public account row or null.
     * Password is never returned.
     */
    public function authenticate(string $login, string $password): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, login, password, status, empire, cash, mileage, create_time, last_play
             FROM `account` WHERE login = ?',
            [$login],
        );

        if ($row === null) {
            return null;
        }

        if (($row['status'] ?? '') === 'BLOCK') {
            return null;
        }

        $hash = (string) ($row['password'] ?? '');

        if ($hash === '' || !hash_equals($hash, $this->hashPassword($password))) {
            return null;
        }

        return $this->reveal($row);
    }

    public function all(): array
    {
        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT id, login, status, empire, cash, mileage, create_time, last_play
                 FROM `account`',
            ),
        );
    }

    public function countForGrid(GridQuery $query): int
    {
        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `account`' . $where,
            $params,
        );
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

        return $this->revealAdminAll(
            $this->db()->fetchAll(
                'SELECT id, login, email, status, empire, cash, mileage, create_time, last_play, ip
                 FROM `account`' . $where . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    public function findForAdmin(int $id): ?array
    {
        return $this->revealAdmin(
            $this->db()->fetch(
                'SELECT id, login, email, status, empire, cash, mileage, create_time, last_play, ip
                 FROM `account` WHERE id = ?',
                [$id],
            ),
        );
    }

    public function updateAdmin(
        int $id,
        string $login,
        string $email,
        string $status,
        int $cash,
        int $mileage,
        ?string $password = null,
        ?string $socialId = null,
    ): array {
        $this->assertId($id);
        $login = $this->assertLogin($login);
        $email = $this->assertOptionalEmail($email);
        $status = $this->assertStatus($status);
        $cash = $this->assertCurrency($cash);
        $mileage = $this->assertCurrency($mileage);

        if ($this->findById($id) === null) {
            throw new \RuntimeException('admin.accounts.not_found');
        }

        $taken = $this->findByLogin($login);

        if ($taken !== null && (int) $taken['id'] !== $id) {
            throw new \RuntimeException('error.login_exists');
        }

        $sets = [
            'login = ?',
            'email = ?',
            'status = ?',
            'cash = ?',
            'mileage = ?',
        ];
        $params = [$login, $email, $status, $cash, $mileage];

        if ($password !== null && $password !== '') {
            $sets[] = 'password = ?';
            $params[] = $this->hashPassword($this->assertPassword($password));
        }

        if ($socialId !== null && $socialId !== '') {
            $sets[] = 'social_id = ?';
            $params[] = $this->assertSocialId($socialId);
        }

        $params[] = $id;

        $this->db()->execute(
            'UPDATE `account` SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params,
        );

        $account = $this->findForAdmin($id);

        if ($account === null) {
            throw new \RuntimeException('admin.accounts.not_found');
        }

        return $account;
    }

    public function create(string $login, string $email, string $password, string $socialId): array
    {
        $login = $this->assertLogin($login);
        $email = $this->assertEmail($email);
        $password = $this->assertPassword($password);
        $socialId = $this->assertSocialId($socialId);

        if ($this->findByLogin($login)) {
            throw new \RuntimeException('error.login_exists');
        }

        $now = date('Y-m-d H:i:s');
        $db = $this->db();

        $db->execute(
            'INSERT INTO `account` (`login`, `email`, `password`, `social_id`, `create_time`)
             VALUES (?, ?, ?, ?, ?)',
            [$login, $email, $this->hashPassword($password), $socialId, $now],
        );

        $id = (int) $db->lastInsertId();
        $account = $id > 0 ? $this->findById($id) : $this->findByLogin($login);

        if ($account === null) {
            throw new \RuntimeException('error.account_create_failed');
        }

        return $account;
    }

    public function block(int $id): array
    {
        return $this->setStatus($id, 'BLOCK');
    }

    public function unblock(int $id): array
    {
        return $this->setStatus($id, 'OK');
    }

    public function delete(int $id): bool
    {
        $this->assertId($id);

        return $this->db()->execute('DELETE FROM `account` WHERE id = ?', [$id]) > 0;
    }

    /**
     * Atomically debit cash when the account is OK and has enough balance.
     * Returns true only when exactly one row was updated.
     */
    public function debitCash(int $id, int $amount): bool
    {
        $this->assertId($id);
        $amount = $this->assertCurrency($amount);

        if ($amount < 1) {
            throw new \InvalidArgumentException('admin.accounts.invalid_currency');
        }

        return $this->db()->execute(
            'UPDATE `account`
             SET cash = cash - ?
             WHERE id = ? AND status = ? AND cash >= ?',
            [$amount, $id, 'OK', $amount],
        ) === 1;
    }

    public function acquireNamedLock(string $name, int $timeoutSeconds = 5): bool
    {
        if (!preg_match('/^[A-Za-z0-9:_-]{1,64}$/', $name)) {
            throw new \InvalidArgumentException('Invalid lock name');
        }

        $timeoutSeconds = max(0, min(30, $timeoutSeconds));
        $result = $this->db()->fetchColumn(
            'SELECT GET_LOCK(?, ?)',
            [$name, $timeoutSeconds],
        );

        return (int) $result === 1;
    }

    public function releaseNamedLock(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9:_-]{1,64}$/', $name)) {
            return;
        }

        $this->db()->fetchColumn('SELECT RELEASE_LOCK(?)', [$name]);
    }

    private function setStatus(int $id, string $status): array
    {
        $this->assertId($id);
        $status = $this->assertStatus($status);

        if ($this->findById($id) === null) {
            throw new \RuntimeException('admin.accounts.not_found');
        }

        $this->db()->execute(
            'UPDATE `account` SET status = ? WHERE id = ?',
            [$status, $id],
        );

        $account = $this->findById($id);

        if ($account === null) {
            throw new \RuntimeException('admin.accounts.not_found');
        }

        return $account;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function adminWhere(?string $q): array
    {
        return $this->gridWhere(new GridQuery($q, 1, 20, 'id', 'desc', []));
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        $clauses = [];
        $params = [];

        if ($query->q !== null && $query->q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
            $clauses[] = '(login LIKE ? OR email LIKE ?)';
            $params[] = '%' . $escaped . '%';
            $params[] = '%' . $escaped . '%';
        }

        $status = $query->filter('status');

        if ($status !== '' && in_array($status, ['OK', 'BLOCK'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        if ($clauses === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revealAdminAll(array $rows): array
    {
        return array_values(array_filter(
            array_map(fn (array $row): ?array => $this->revealAdmin($row), $rows),
        ));
    }

    private function revealAdmin(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        unset($row['password'], $row['social_id'], $row['securitycode']);

        return $row;
    }

    private function assertOptionalEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '') {
            return '';
        }

        return $this->assertEmail($email);
    }

    private function assertStatus(string $status): string
    {
        if (!in_array($status, ['OK', 'BLOCK'], true)) {
            throw new \InvalidArgumentException('admin.accounts.invalid_status');
        }

        return $status;
    }

    private function assertCurrency(int $value): int
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('admin.accounts.invalid_currency');
        }

        return $value;
    }

    private function assertId(int $id): void
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Invalid account id');
        }
    }

    private function assertLogin(string $login): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{2,30}$/', $login)) {
            throw new \InvalidArgumentException('error.invalid_login');
        }

        return $login;
    }

    private function assertEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('error.invalid_email');
        }

        return $email;
    }

    private function assertPassword(string $password): string
    {
        $length = strlen($password);

        if ($length < 5 || $length > 16) {
            throw new \InvalidArgumentException('error.invalid_password');
        }

        return $password;
    }

    private function assertSocialId(string $socialid): string
    {
        if (!ctype_digit($socialid) || (int) $socialid <= 0) {
            throw new \InvalidArgumentException('error.invalid_social_id');
        }

        if (strlen($socialid) < 7) {
            throw new \InvalidArgumentException('error.social_id_min_length');
        }

        return $socialid;
    }

    private function hashPassword(string $password): string
    {
        return '*' . strtoupper(sha1(sha1($password, true)));
    }
}
