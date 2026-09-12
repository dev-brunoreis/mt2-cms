<?php

declare(strict_types=1);

namespace Mt2Cms\Repository;

use Mt2Cms\Support\Database;

class ProtoNameRepository extends Repository
{
    protected function database(): string
    {
        return 'player';
    }

    public function upsertItem(int $vnum, string $localeName): void
    {
        $this->upsert('item_proto', $vnum, $localeName);
    }

    public function deleteItem(int $vnum): void
    {
        $this->deleteRow('item_proto', $vnum);
    }

    public function upsertMob(int $vnum, string $localeName): void
    {
        $this->upsert('mob_proto', $vnum, $localeName);
    }

    public function deleteMob(int $vnum): void
    {
        $this->deleteRow('mob_proto', $vnum);
    }

    private function upsert(string $table, int $vnum, string $localeName): void
    {
        if ($vnum < 1 || !$this->schemaTableExists($table)) {
            return;
        }

        $localeName = $this->clipName($localeName);
        $table = Database::quoteIdentifier($table);
        $exists = $this->db()->fetch(
            'SELECT vnum FROM `' . $table . '` WHERE vnum = ?',
            [$vnum],
        );

        if ($exists !== null) {
            $this->db()->execute(
                'UPDATE `' . $table . '` SET locale_name = ? WHERE vnum = ?',
                [$localeName, $vnum],
            );

            return;
        }

        $this->db()->execute(
            'INSERT INTO `' . $table . '` (vnum, name, locale_name) VALUES (?, ?, ?)',
            [$vnum, $localeName, $localeName],
        );
    }

    private function deleteRow(string $table, int $vnum): void
    {
        if ($vnum < 1 || !$this->schemaTableExists($table)) {
            return;
        }

        $table = Database::quoteIdentifier($table);

        $this->db()->execute(
            'DELETE FROM `' . $table . '` WHERE vnum = ?',
            [$vnum],
        );
    }

    private function clipName(string $name): string
    {
        $name = trim($name);

        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 24);
        }

        return substr($name, 0, 24);
    }
}
