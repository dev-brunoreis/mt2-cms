<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Game\ItemDescCatalog;
use Mt2Cms\Game\ItemSockets;
use Mt2Cms\Game\ItemStats;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoSchemas;

class ItemTooltipBuilder
{
    public function __construct(
        private GameProtoService $protos,
        private ItemStats $itemStats,
        private ProtoEnums $enums,
        private ItemDescCatalog $descriptions,
    ) {
    }

    /**
     * Build character-style tooltip fields for a catalog item (shop product / proto).
     *
     * @param array{socket0?: int, socket1?: int, socket2?: int}|null $sockets
     * @return array{
     *   vnum: int,
     *   name: string,
     *   description: string,
     *   stats: list<array<string, mixed>>,
     *   applies: list<array{token: string, value: int, percent: bool}>,
     *   bonuses: list<array{token: string, value: int, percent: bool, rare: bool}>,
     *   special_title: bool,
     *   wearable: array{jobs: list<string>, sex: string|null}|null,
     *   sockets: list<array<string, mixed>>
     * }
     */
    public function forVnum(int $vnum, ?array $sockets = null): array
    {
        $vnum = max(0, $vnum);
        $proto = $vnum > 0 ? $this->protos->find(ProtoSchemas::KIND_ITEM, $vnum) : null;

        $locale = trim((string) ($proto['locale_name'] ?? ''));
        $name = $locale !== '' ? $locale : trim((string) ($proto['name'] ?? ''));
        if ($name === '') {
            $name = $vnum > 0 ? (string) $vnum : '';
        }

        $type = $this->enums->itemTypeIndex($proto['type'] ?? 0);
        $typeName = $this->enums->itemTypeName($type);
        $subtype = $this->enums->itemSubtypeIndex($typeName, $proto['subtype'] ?? 0);
        $limitType = $this->enums->limitTypeIndex($proto['limit_type0'] ?? 0);
        $limitValue = (int) ($proto['limit_value0'] ?? 0);
        $values = [
            (int) ($proto['value0'] ?? 0),
            (int) ($proto['value1'] ?? 0),
            (int) ($proto['value2'] ?? 0),
            (int) ($proto['value3'] ?? 0),
            (int) ($proto['value4'] ?? 0),
            (int) ($proto['value5'] ?? 0),
        ];
        $applies = [
            [
                'type' => $this->enums->applyTypeIndex($proto['apply_type0'] ?? 0),
                'value' => (int) ($proto['apply_value0'] ?? 0),
            ],
            [
                'type' => $this->enums->applyTypeIndex($proto['apply_type1'] ?? 0),
                'value' => (int) ($proto['apply_value1'] ?? 0),
            ],
            [
                'type' => $this->enums->applyTypeIndex($proto['apply_type2'] ?? 0),
                'value' => (int) ($proto['apply_value2'] ?? 0),
            ],
        ];
        $socketValues = [
            (int) ($sockets['socket0'] ?? 0),
            (int) ($sockets['socket1'] ?? 0),
            (int) ($sockets['socket2'] ?? 0),
        ];

        $tooltip = $this->itemStats->describe(
            $type,
            $subtype,
            $limitType,
            $limitValue,
            $values,
            $applies,
            [],
            $proto['antiflag'] ?? 0,
        );

        $socketEntries = ItemSockets::describe(
            $type,
            $subtype,
            $limitType,
            $values[0],
            $values[2],
            $socketValues,
            $vnum,
            $applies,
            $this->itemStats,
        );

        return [
            'vnum' => $vnum,
            'name' => $name,
            'description' => $this->descriptions->description($vnum),
            'stats' => $tooltip['stats'],
            'applies' => $tooltip['applies'],
            'bonuses' => $tooltip['bonuses'],
            'special_title' => (bool) ($tooltip['special_title'] ?? false),
            'wearable' => $tooltip['wearable'] ?? null,
            'sockets' => $this->enrichSockets($socketEntries),
        ];
    }

    /**
     * @param list<array<string, mixed>> $sockets
     * @return list<array<string, mixed>>
     */
    private function enrichSockets(array $sockets): array
    {
        foreach ($sockets as $index => $socket) {
            if (($socket['kind'] ?? '') !== 'item') {
                continue;
            }

            $stoneVnum = (int) ($socket['value'] ?? 0);
            if ($stoneVnum < 1) {
                continue;
            }

            $proto = $this->protos->find(ProtoSchemas::KIND_ITEM, $stoneVnum);
            $locale = trim((string) ($proto['locale_name'] ?? ''));
            $name = $locale !== '' ? $locale : trim((string) ($proto['name'] ?? ''));

            if ($name === '') {
                $sockets[$index]['kind'] = 'raw';
                continue;
            }

            $sockets[$index]['name'] = $name;

            if (($socket['applies'] ?? []) !== []) {
                continue;
            }

            $sockets[$index]['applies'] = $this->itemStats->applyEntries([
                [
                    'type' => $this->enums->applyTypeIndex($proto['apply_type0'] ?? 0),
                    'value' => (int) ($proto['apply_value0'] ?? 0),
                ],
            ]);
        }

        return $sockets;
    }
}
