<?php

declare(strict_types=1);

namespace Mt2Cms\Setup;

use Mt2Cms\Support\Database;

final class MigrationRunner
{
    public function __construct(private Database $db)
    {
    }

    public function migrate(): void
    {
        $this->db->useDatabase('cms');
        $this->ensureVersionTable();

        $current = $this->currentVersion();
        $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        foreach ($files as $file) {
            $version = $this->versionFromFilename($file);

            if ($version <= $current) {
                continue;
            }

            $this->runSqlFile((string) file_get_contents($file));
            $this->setVersion($version);
        }
    }

    public function currentVersion(): int
    {
        $this->db->useDatabase('cms');
        $this->ensureVersionTable();

        return $this->readCurrentVersion();
    }

    public function readCurrentVersion(): int
    {
        $this->db->useDatabase('cms');

        if (!$this->versionTableExists()) {
            return 0;
        }

        $value = $this->db->fetchColumn('SELECT version FROM cms_schema_version WHERE id = 1');

        return is_numeric($value) ? (int) $value : 0;
    }

    public static function latestVersion(): int
    {
        $files = glob(__DIR__ . '/migrations/*.sql') ?: [];
        $latest = 0;

        foreach ($files as $file) {
            if (preg_match('/^(\d+)_/i', basename($file), $matches)) {
                $latest = max($latest, (int) $matches[1]);
            }
        }

        return $latest;
    }

    private function versionTableExists(): bool
    {
        $row = $this->db->fetch(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             LIMIT 1',
            ['cms_schema_version'],
        );

        return $row !== null;
    }

    private function ensureVersionTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS cms_schema_version (
                id TINYINT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );

        $this->db->execute(
            'INSERT INTO cms_schema_version (id, version)
             VALUES (1, 0)
             ON DUPLICATE KEY UPDATE version = version',
        );
    }

    private function versionFromFilename(string $file): int
    {
        if (!preg_match('/^(\d+)_/i', basename($file), $matches)) {
            throw new \RuntimeException('Invalid migration filename: ' . $file);
        }

        return (int) $matches[1];
    }

    private function runSqlFile(string $sql): void
    {
        foreach ($this->splitStatements($sql) as $statement) {
            $this->db->execute($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';

        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $buffer .= $line . "\n";

            if (str_ends_with(rtrim($line), ';')) {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';
            }
        }

        $tail = trim($buffer);

        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private function setVersion(int $version): void
    {
        $this->db->execute(
            'UPDATE cms_schema_version SET version = ?, applied_at = NOW() WHERE id = 1',
            [$version],
        );
    }
}
