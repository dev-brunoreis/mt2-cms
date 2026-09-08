<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Model\Database;

abstract class Repository
{
    public function __construct(protected Database $db = new Database())
    {
    }

    abstract protected function database(): string;

    /** @return list<string> */
    protected function hiddenColumns(): array
    {
        return [
            'password',
            'social_id',
            'securitycode',
            'email',
            'ip',
            'mobile',
        ];
    }

    protected function db(): Database
    {
        return $this->db->useDatabase($this->database());
    }

    protected function schemaTableExists(string $table): bool
    {
        $table = Database::quoteIdentifier($table);

        return $this->db()->fetch(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             LIMIT 1',
            [$this->database(), $table],
        ) !== null;
    }

    protected function reveal(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        foreach ($this->hiddenColumns() as $column) {
            unset($row[$column]);
        }

        return $row;
    }

    protected function revealAll(array $rows): array
    {
        return array_map(fn (array $row) => $this->reveal($row), $rows);
    }
}
