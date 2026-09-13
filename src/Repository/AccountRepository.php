<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\Definitions\AccountsGrid;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class AccountRepository extends Repository implements ProvidesAdminGrid
{

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

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    public function loginsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
        $ids = array_slice($ids, 0, 200);

        if ($ids === [] || !$this->schemaTableExists('account')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = $this->db()->fetchAll(
            'SELECT id, login FROM `account` WHERE id IN (' . $placeholders . ')',
            $ids,
        );
        $map = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $map[$id] = (string) ($row['login'] ?? '');
            }
        }

        return $map;
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
     * @return list<int>
     */
    public function findIdsByLoginLike(string $query, int $max = 100): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $like = '%' . $escaped . '%';
        $rows = $this->db()->fetchAll(
            'SELECT id FROM `account` WHERE login LIKE ? ORDER BY id ASC LIMIT ' . max(1, min(500, $max)),
            [$like],
        );
        $ids = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
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
        $order = GridSql::orderBy($query, AccountsGrid::definition()->sortMap(), 'id DESC');

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

    public function verifyPassword(int $id, string $current): bool
    {
        $this->assertId($id);

        if ($current === '') {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT password FROM `account` WHERE id = ?',
            [$id],
        );

        if ($row === null) {
            return false;
        }

        $hash = (string) ($row['password'] ?? '');

        return $hash !== '' && hash_equals($hash, $this->hashPassword($current));
    }

    public function changePassword(int $id, string $current, string $new): void
    {
        $this->assertId($id);
        $new = $this->assertPassword($new);

        if ($current === '') {
            throw new \InvalidArgumentException('account.wrong_password');
        }

        $row = $this->db()->fetch(
            'SELECT password FROM `account` WHERE id = ?',
            [$id],
        );

        if ($row === null) {
            throw new \RuntimeException('error.account_not_found');
        }

        $hash = (string) ($row['password'] ?? '');

        if ($hash === '' || !hash_equals($hash, $this->hashPassword($current))) {
            throw new \InvalidArgumentException('account.wrong_password');
        }

        $newHash = $this->hashPassword($new);

        if (hash_equals($hash, $newHash)) {
            throw new \InvalidArgumentException('account.password_unchanged');
        }

        $this->db()->execute(
            'UPDATE `account` SET password = ? WHERE id = ?',
            [$newHash, $id],
        );
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

    public function creditCash(int $id, int $amount): bool
    {
        $this->assertId($id);
        $amount = $this->assertCurrency($amount);

        if ($amount < 1) {
            throw new \InvalidArgumentException('admin.accounts.invalid_currency');
        }

        return $this->db()->execute(
            'UPDATE `account`
             SET cash = cash + ?, total_cash = total_cash + ?
             WHERE id = ? AND status = ?',
            [$amount, $amount, $id, 'OK'],
        ) === 1;
    }

    /**
     * Lookup for password reset — returns id + email only when a valid email exists.
     *
     * @return array{id: int, email: string}|null
     */
    public function findForPasswordReset(string $loginOrEmail): ?array
    {
        $value = trim($loginOrEmail);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            $row = $this->db()->fetch(
                'SELECT id, email FROM `account` WHERE email = ? LIMIT 1',
                [$value],
            );
        } else {
            $row = $this->db()->fetch(
                'SELECT id, email FROM `account` WHERE login = ? LIMIT 1',
                [$value],
            );
        }

        if ($row === null) {
            return null;
        }

        $email = trim((string) ($row['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['id' => (int) $row['id'], 'email' => $email];
    }

    public function findEmailById(int $id): ?string
    {
        $this->assertId($id);
        $row = $this->db()->fetch('SELECT email FROM `account` WHERE id = ?', [$id]);

        if ($row === null) {
            return null;
        }

        $email = trim((string) ($row['email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    public function updateEmail(int $id, string $email): void
    {
        $this->assertId($id);
        $email = $this->assertEmail($email);

        $this->db()->execute(
            'UPDATE `account` SET email = ? WHERE id = ?',
            [$email, $id],
        );
    }

    public function resetPassword(int $id, string $newPassword): void
    {
        $this->assertId($id);
        $newPassword = $this->assertPassword($newPassword);

        $this->db()->execute(
            'UPDATE `account` SET password = ? WHERE id = ?',
            [$this->hashPassword($newPassword), $id],
        );
    }

    public function changeSocialId(int $id, string $currentPassword, string $newSocialId): void
    {
        $this->assertId($id);
        $newSocialId = $this->assertSocialId($newSocialId);

        if ($currentPassword === '') {
            throw new \InvalidArgumentException('account.wrong_password');
        }

        $row = $this->db()->fetch(
            'SELECT password FROM `account` WHERE id = ?',
            [$id],
        );

        if ($row === null) {
            throw new \RuntimeException('error.account_not_found');
        }

        $hash = (string) ($row['password'] ?? '');

        if ($hash === '' || !hash_equals($hash, $this->hashPassword($currentPassword))) {
            throw new \InvalidArgumentException('account.wrong_password');
        }

        $this->db()->execute(
            'UPDATE `account` SET social_id = ? WHERE id = ?',
            [$newSocialId, $id],
        );
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
        return GridSql::where($query, AccountsGrid::definition()->filterSql());
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
