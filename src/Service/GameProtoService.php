<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\Game\Proto\TabProtoTable;
use Mt2Cms\Repository\ProtoNameRepository;
use Mt2Cms\Support\Log;

class GameProtoService
{
    public const ROUTE_ITEMS = 'items';
    public const ROUTE_MOBS = 'mobs';

    private TabProtoTable $items;
    private TabProtoTable $mobs;

    public function __construct(
        string $dbDir,
        private ProtoNameRepository $protoNames,
    ) {
        $this->items = new TabProtoTable(
            $dbDir . '/item_proto.txt',
            $dbDir . '/item_names_en.txt',
            ProtoSchemas::columns(ProtoSchemas::KIND_ITEM),
        );
        $this->mobs = new TabProtoTable(
            $dbDir . '/mob_proto.txt',
            $dbDir . '/mob_names_en.txt',
            ProtoSchemas::columns(ProtoSchemas::KIND_MOB),
        );
    }

    public function kindFromRoute(string $routeKind): string
    {
        return match ($routeKind) {
            self::ROUTE_ITEMS => ProtoSchemas::KIND_ITEM,
            self::ROUTE_MOBS => ProtoSchemas::KIND_MOB,
            default => throw new \InvalidArgumentException('admin.proto.unknown'),
        };
    }

    /**
     * @return array{rows: list<array<string, string>>, total: int}
     */
    public function page(string $kind, int $page, int $perPage, ?string $query): array
    {
        $filtered = $this->filter($this->table($kind)->all(), $query);
        $total = count($filtered);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($filtered, $offset, $perPage),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function find(string $kind, int $vnum): ?array
    {
        return $this->table($kind)->find($vnum);
    }

    /**
     * @return list<array<string, string>>
     */
    public function all(string $kind): array
    {
        return $this->table($kind)->all();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(string $kind, array $input): array
    {
        $record = $this->validatedRecord($kind, $input, null);
        $this->table($kind)->create($record);
        $this->syncDatabase($kind, (int) $record['vnum'], $record['locale_name'], false);

        return $record;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(string $kind, int $vnum, array $input): array
    {
        $current = $this->table($kind)->find($vnum);

        if ($current === null) {
            throw new \RuntimeException('admin.proto.not_found');
        }

        $record = $this->validatedRecord($kind, $input, $current);
        $this->table($kind)->update($vnum, $record);
        $this->syncDatabase($kind, $vnum, $record['locale_name'], false);

        return $record;
    }

    public function delete(string $kind, int $vnum): bool
    {
        $deleted = $this->table($kind)->delete($vnum);

        if ($deleted) {
            $this->syncDatabase($kind, $vnum, '', true);
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    public function listColumns(string $kind): array
    {
        return ProtoSchemas::listColumns($kind);
    }

    /**
     * @return list<array{id: string, fields: list<string>}>
     */
    public function formTabs(string $kind): array
    {
        return ProtoSchemas::formTabs($kind);
    }

    /**
     * @return array<string, string>
     */
    public function emptyRecord(string $kind): array
    {
        return ProtoSchemas::defaults($kind);
    }

    private function table(string $kind): TabProtoTable
    {
        return $kind === ProtoSchemas::KIND_MOB ? $this->mobs : $this->items;
    }

    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, string>>
     */
    private function filter(array $rows, ?string $query): array
    {
        $needle = $query !== null ? mb_strtolower(trim($query)) : '';

        if ($needle === '') {
            return $rows;
        }

        $matched = [];

        foreach ($rows as $row) {
            $haystack = mb_strtolower(implode(' ', [
                $row['vnum'] ?? '',
                $row['locale_name'] ?? '',
                $row['type'] ?? '',
                $row['subtype'] ?? '',
                $row['rank'] ?? '',
                $row['level'] ?? '',
            ]));

            if (str_contains($haystack, $needle)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string>|null $current
     * @return array<string, string>
     */
    private function validatedRecord(string $kind, array $input, ?array $current): array
    {
        $record = $current ?? ProtoSchemas::defaults($kind);

        foreach (ProtoSchemas::columns($kind) as $key) {
            if ($key === 'name' || $key === 'vnum') {
                continue;
            }

            if (array_key_exists($key, $input)) {
                $record[$key] = $this->sanitizeField((string) $input[$key], 80);
            }
        }

        $localeName = $this->sanitizeField((string) ($input['locale_name'] ?? $record['locale_name'] ?? ''), 64);

        if ($localeName === '') {
            throw new \InvalidArgumentException('admin.proto.invalid_name');
        }

        $record['locale_name'] = $localeName;

        if ($current === null) {
            $vnum = (int) ($input['vnum'] ?? 0);

            if ($vnum < 1) {
                throw new \InvalidArgumentException('admin.proto.invalid_vnum');
            }

            $record['vnum'] = (string) $vnum;
            $record['name'] = $localeName;
        }

        return $record;
    }

    private function sanitizeField(string $value, int $maxLength): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function syncDatabase(string $kind, int $vnum, string $localeName, bool $delete): void
    {
        try {
            if ($kind === ProtoSchemas::KIND_MOB) {
                if ($delete) {
                    $this->protoNames->deleteMob($vnum);
                } else {
                    $this->protoNames->upsertMob($vnum, $localeName);
                }

                return;
            }

            if ($delete) {
                $this->protoNames->deleteItem($vnum);
            } else {
                $this->protoNames->upsertItem($vnum, $localeName);
            }
        } catch (\Throwable $exception) {
            Log::error('proto-db', 'Failed to sync proto names to player schema', $exception);
        }
    }
}
