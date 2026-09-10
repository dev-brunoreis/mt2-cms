<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Admin\Grid\GridDefinition;
use Mt2Cms\Admin\Grid\GridQuery;
use Mt2Cms\Game\GameProfile;
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
        GameProfile $profile,
        private ProtoSchemas $schemas,
        private ProtoNameRepository $protoNames,
    ) {
        $this->items = new TabProtoTable(
            $profile->path('item_proto'),
            $profile->path('item_names'),
            $profile->columns(ProtoSchemas::KIND_ITEM),
        );
        $this->mobs = new TabProtoTable(
            $profile->path('mob_proto'),
            $profile->path('mob_names'),
            $profile->columns(ProtoSchemas::KIND_MOB),
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
     * @param list<int> $excludeVnums
     */
    public function page(string $kind, int $page, int $perPage, ?string $query, array $excludeVnums = []): array
    {
        $filtered = $this->filter($this->table($kind)->all(), $query);

        if ($excludeVnums !== []) {
            $exclude = [];

            foreach ($excludeVnums as $vnum) {
                $exclude[(int) $vnum] = true;
            }

            $filtered = array_values(array_filter(
                $filtered,
                static fn (array $row): bool => !isset($exclude[(int) ($row['vnum'] ?? 0)]),
            ));
        }
        $total = count($filtered);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($filtered, $offset, $perPage),
            'total' => $total,
        ];
    }

    public function countForGrid(string $kind, GridQuery $query): int
    {
        return count($this->filter($this->table($kind)->all(), $query->q));
    }

    /**
     * @return list<array<string, string>>
     */
    public function listForGrid(string $kind, GridQuery $query): array
    {
        $rows = $this->sortRows(
            $this->filter($this->table($kind)->all(), $query->q),
            $query,
            $this->listColumns($kind),
        );

        return array_slice($rows, $query->offset(), $query->perPage);
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
     * Item proto rows grouped by refine recipe id (item_proto.refine).
     *
     * @return array<int, list<array{vnum: int, locale_name: string}>>
     */
    public function itemsByRefineId(): array
    {
        $map = [];

        foreach ($this->all(ProtoSchemas::KIND_ITEM) as $row) {
            $refineId = (int) ($row['refine'] ?? 0);

            if ($refineId < 1) {
                continue;
            }

            $map[$refineId][] = [
                'vnum' => (int) $row['vnum'],
                'locale_name' => (string) ($row['locale_name'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * Refine recipe ids referenced by items whose vnum starts with the given prefix.
     *
     * @return list<int>
     */
    public function refineIdsForItemVnumPrefix(string $prefix): array
    {
        if ($prefix === '') {
            return [];
        }

        $ids = [];

        foreach ($this->all(ProtoSchemas::KIND_ITEM) as $row) {
            $vnum = (string) ($row['vnum'] ?? '');

            if (!str_starts_with($vnum, $prefix)) {
                continue;
            }

            $refineId = (int) ($row['refine'] ?? 0);

            if ($refineId > 0) {
                $ids[$refineId] = $refineId;
            }
        }

        return array_values($ids);
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
        return $this->schemas->listColumns($kind);
    }

    public function adminGridDefinition(string $route): GridDefinition
    {
        $kind = $this->kindFromRoute($route);
        $prefix = $route === self::ROUTE_ITEMS ? 'admin.items' : 'admin.mobs';
        $columns = [];

        foreach ($this->listColumns($kind) as $column) {
            if ($column === 'locale_name') {
                $columns[] = [
                    'key' => 'locale_name',
                    'label' => $prefix . '.fields.locale_name',
                    'sort' => 'locale_name',
                    'type' => 'icon_link',
                    'icon' => $route === self::ROUTE_ITEMS ? 'item' : 'face',
                    'href' => '/admin/' . $route . '/{id}',
                ];

                continue;
            }

            $columns[] = [
                'key' => $column,
                'label' => $prefix . '.fields.' . $column,
                'sort' => $column,
                'type' => in_array($column, ['vnum', 'level', 'rank'], true) ? 'number' : 'text',
            ];
        }

        $sortMap = [];

        foreach ($this->listColumns($kind) as $column) {
            $sortMap[$column] = $column;
        }

        return GridDefinition::create('/admin/' . $route, $prefix)
            ->idField('vnum')
            ->defaultSort('vnum')
            ->orderBy($sortMap)
            ->columns($columns)
            ->massActions('/admin/' . $route . '/mass', [
                ['id' => 'delete', 'label' => 'admin.grid.delete', 'confirm' => $prefix . '.confirm_mass_delete'],
            ]);
    }

    /**
     * @return list<array{id: string, fields: list<string>}>
     */
    public function formTabs(string $kind): array
    {
        return $this->schemas->formTabs($kind);
    }

    /**
     * @return array<string, string>
     */
    public function emptyRecord(string $kind): array
    {
        return $this->schemas->defaults($kind);
    }

    private function table(string $kind): TabProtoTable
    {
        return $kind === ProtoSchemas::KIND_MOB ? $this->mobs : $this->items;
    }

    /**
     * @param list<array<string, string>> $rows
     * @param list<string> $sortWhitelist
     * @return list<array<string, string>>
     */
    private function sortRows(array $rows, GridQuery $query, array $sortWhitelist): array
    {
        $sort = in_array($query->sort, $sortWhitelist, true) ? $query->sort : 'vnum';
        $dir = $query->dir === 'asc' ? 1 : -1;

        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $left = $a[$sort] ?? '';
            $right = $b[$sort] ?? '';

            if (is_numeric($left) && is_numeric($right)) {
                return ((int) $left <=> (int) $right) * $dir;
            }

            return strcasecmp((string) $left, (string) $right) * $dir;
        });

        return $rows;
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
        $record = $current ?? $this->schemas->defaults($kind);

        foreach ($this->schemas->columns($kind) as $key) {
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
