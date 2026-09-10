<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Admin\Grid\GridSql;
use Mt2Cms\Admin\Grid\ProvidesAdminGrid;

class CommonRepository extends Repository implements ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition
    {
        return GridDefinition::create('/admin/game-data/gms', 'admin.gms')
            ->idField('mID')
            ->orderBy([
                'mID' => 'mID',
                'mAccount' => 'mAccount',
                'mName' => 'mName',
                'mAuthority' => 'mAuthority',
            ])
            ->columns([
                ['key' => 'mID', 'label' => 'admin.gms.id', 'sort' => 'mID', 'type' => 'muted'],
                ['key' => 'mAccount', 'label' => 'admin.gms.account', 'sort' => 'mAccount', 'type' => 'link', 'href' => '/admin/game-data/gms/{mID}'],
                ['key' => 'mName', 'label' => 'admin.gms.name', 'sort' => 'mName', 'type' => 'text'],
                ['key' => 'mAuthority', 'label' => 'admin.gms.authority', 'sort' => 'mAuthority', 'type' => 'text'],
            ])
            ->massActions('/admin/game-data/gms/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => 'admin.gms.confirm_mass_delete'],
            ]);
    }

    private const AUTHORITIES = [
        'IMPLEMENTOR',
        'HIGH_WIZARD',
        'GOD',
        'LOW_WIZARD',
        'PLAYER',
    ];

    protected function database(): string
    {
        return 'common';
    }

    public function gmList(): array
    {
        return $this->listForGrid(new GridQuery(null, 1, 10000, 'mID', 'asc', []));
    }

    public function countForGrid(GridQuery $query): int
    {
        if (!$this->schemaTableExists('gmlist')) {
            return 0;
        }

        [$where, $params] = $this->gridWhere($query);

        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `gmlist`' . $where,
            $params,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForGrid(GridQuery $query): array
    {
        if (!$this->schemaTableExists('gmlist')) {
            return [];
        }

        [$where, $params] = $this->gridWhere($query);
        $params[] = $query->perPage;
        $params[] = $query->offset();
        $order = GridSql::orderBy($query, $this->gridDefinition()->sortMap(), 'mID ASC');

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT mID, mAccount, mName, mContactIP, mServerIP, mAuthority
                 FROM `gmlist`' . $where . $order . '
                 LIMIT ? OFFSET ?',
                $params,
            ),
        );
    }

    public function findGmById(int $id): ?array
    {
        if ($id < 1 || !$this->schemaTableExists('gmlist')) {
            return null;
        }

        return $this->reveal(
            $this->db()->fetch(
                'SELECT mID, mAccount, mName, mContactIP, mServerIP, mAuthority FROM `gmlist` WHERE mID = ?',
                [$id],
            ),
        );
    }

    public function findGmByAccount(string $account): ?array
    {
        return $this->reveal(
            $this->db()->fetch(
                'SELECT mID, mAccount, mName, mContactIP, mServerIP, mAuthority FROM `gmlist` WHERE mAccount = ?',
                [$account],
            ),
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{mAccount: string, mName: string, mContactIP: string, mServerIP: string, mAuthority: string}
     */
    public function createGm(array $input): array
    {
        $validated = $this->validateGmInput($input);

        $this->db()->execute(
            'INSERT INTO `gmlist` (mAccount, mName, mContactIP, mServerIP, mAuthority)
             VALUES (?, ?, ?, ?, ?)',
            [
                $validated['mAccount'],
                $validated['mName'],
                $validated['mContactIP'],
                $validated['mServerIP'],
                $validated['mAuthority'],
            ],
        );

        $id = (int) $this->db()->lastInsertId();
        $gm = $this->findGmById($id);

        if ($gm === null) {
            throw new \RuntimeException('admin.gms.create_failed');
        }

        return $gm;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateGm(int $id, array $input): void
    {
        if ($this->findGmById($id) === null) {
            throw new \InvalidArgumentException('admin.gms.not_found');
        }

        $validated = $this->validateGmInput($input);

        $this->db()->execute(
            'UPDATE `gmlist`
             SET mAccount = ?, mName = ?, mContactIP = ?, mServerIP = ?, mAuthority = ?
             WHERE mID = ?',
            [
                $validated['mAccount'],
                $validated['mName'],
                $validated['mContactIP'],
                $validated['mServerIP'],
                $validated['mAuthority'],
                $id,
            ],
        );
    }

    public function deleteGm(int $id): bool
    {
        if ($id < 1 || !$this->schemaTableExists('gmlist')) {
            return false;
        }

        return $this->db()->execute('DELETE FROM `gmlist` WHERE mID = ?', [$id]) > 0;
    }

    /**
     * @return list<string>
     */
    public function gmHosts(): array
    {
        if (!$this->schemaTableExists('gmhost')) {
            return [];
        }

        $rows = $this->db()->fetchAll('SELECT mIP FROM `gmhost` ORDER BY mIP ASC');

        return array_map(static fn (array $row): string => (string) $row['mIP'], $rows);
    }

    public function addGmHost(string $ip): void
    {
        $ip = trim($ip);

        if ($ip === '' || strlen($ip) > 16) {
            throw new \InvalidArgumentException('admin.gms.invalid_host');
        }

        if (!$this->schemaTableExists('gmhost')) {
            throw new \RuntimeException('admin.gms.hosts_unavailable');
        }

        $exists = $this->db()->fetchColumn('SELECT mIP FROM `gmhost` WHERE mIP = ? LIMIT 1', [$ip]);

        if ($exists !== null) {
            throw new \InvalidArgumentException('admin.gms.host_exists');
        }

        $this->db()->execute('INSERT INTO `gmhost` (mIP) VALUES (?)', [$ip]);
    }

    public function deleteGmHost(string $ip): bool
    {
        if (!$this->schemaTableExists('gmhost')) {
            return false;
        }

        return $this->db()->execute('DELETE FROM `gmhost` WHERE mIP = ?', [trim($ip)]) > 0;
    }

    public function locales(): array
    {
        if (!$this->schemaTableExists('locale')) {
            return [];
        }

        return $this->revealAll($this->db()->fetchAll('SELECT mKey, mValue FROM `locale`'));
    }

    /**
     * @return list<string>
     */
    public static function authorities(): array
    {
        return self::AUTHORITIES;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function gridWhere(GridQuery $query): array
    {
        if ($query->q === null || $query->q === '') {
            return ['', []];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query->q);
        $like = '%' . $escaped . '%';

        return [
            ' WHERE mAccount LIKE ? OR mName LIKE ?',
            [$like, $like],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{mAccount: string, mName: string, mContactIP: string, mServerIP: string, mAuthority: string}
     */
    private function validateGmInput(array $input): array
    {
        $account = trim((string) ($input['mAccount'] ?? ''));

        if ($account === '' || strlen($account) > 32) {
            throw new \InvalidArgumentException('admin.gms.invalid_account');
        }

        $name = trim((string) ($input['mName'] ?? ''));

        if ($name === '' || strlen($name) > 32) {
            throw new \InvalidArgumentException('admin.gms.invalid_name');
        }

        $contactIp = trim((string) ($input['mContactIP'] ?? ''));

        if (strlen($contactIp) > 16) {
            throw new \InvalidArgumentException('admin.gms.invalid_contact_ip');
        }

        $serverIp = trim((string) ($input['mServerIP'] ?? 'ALL'));

        if ($serverIp === '' || strlen($serverIp) > 16) {
            $serverIp = 'ALL';
        }

        $authority = strtoupper(trim((string) ($input['mAuthority'] ?? 'PLAYER')));

        if (!in_array($authority, self::AUTHORITIES, true)) {
            throw new \InvalidArgumentException('admin.gms.invalid_authority');
        }

        return [
            'mAccount' => $account,
            'mName' => $name,
            'mContactIP' => $contactIp,
            'mServerIP' => $serverIp,
            'mAuthority' => $authority,
        ];
    }
}
