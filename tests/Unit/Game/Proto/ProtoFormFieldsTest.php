<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Game\Proto;

use Mt2Cms\Game\GameProfile;
use Mt2Cms\Game\Proto\ProtoEnums;
use Mt2Cms\Game\Proto\ProtoFormFields;
use Mt2Cms\Game\Proto\ProtoSchemas;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Support\SelectOptions;
use PHPUnit\Framework\TestCase;

final class ProtoFormFieldsTest extends TestCase
{
    public function testSelectOptionsAreSortedByTranslatedLabel(): void
    {
        $profile = GameProfile::load(BASE_DIR . '/game');
        $fields = new ProtoFormFields(new Translator(BASE_DIR . '/lang', 'en', 'en'), new ProtoEnums($profile));
        $record = (new ProtoSchemas($profile))->defaults(ProtoSchemas::KIND_ITEM);
        $record['type'] = 'ITEM_WEAPON';
        $record['subtype'] = 'WEAPON_SWORD';

        $tabs = $fields->decorateTabs(ProtoSchemas::KIND_ITEM, 'admin.proto.item', $profile->formTabs(ProtoSchemas::KIND_ITEM), $record);
        $apply = $this->field($tabs, 'apply_type0');
        $labels = array_column($apply['options'] ?? [], 'label');
        $sorted = $labels;
        usort($sorted, [SelectOptions::class, 'compare']);

        self::assertSame($sorted, $labels);
        self::assertGreaterThan(10, count($labels));
    }

    public function testApplyAndLimitFieldsAreGroupedAsTypeValuePairs(): void
    {
        $profile = GameProfile::load(BASE_DIR . '/game');
        $fields = new ProtoFormFields(new Translator(BASE_DIR . '/lang', 'en', 'en'), new ProtoEnums($profile));
        $record = (new ProtoSchemas($profile))->defaults(ProtoSchemas::KIND_ITEM);
        $tabs = $fields->decorateTabs(ProtoSchemas::KIND_ITEM, 'admin.items', $profile->formTabs(ProtoSchemas::KIND_ITEM), $record);
        $applies = null;

        foreach ($tabs as $tab) {
            if (($tab['id'] ?? '') === 'applies') {
                $applies = $tab;
                break;
            }
        }

        self::assertNotNull($applies);
        self::assertSame(['pair', 'pair', 'pair', 'pair', 'pair'], array_column($applies['rows'] ?? [], 'kind'));
        self::assertSame('limit', $applies['rows'][0]['role'] ?? null);
        self::assertSame('Requirement 1', $applies['rows'][0]['title'] ?? null);
        self::assertSame('Requirement', $applies['rows'][0]['typeLabel'] ?? null);
        self::assertSame('Value', $applies['rows'][0]['valueLabel'] ?? null);
        self::assertSame('limit_type0', $applies['rows'][0]['type']['key'] ?? null);
        self::assertSame('limit_value0', $applies['rows'][0]['value']['key'] ?? null);
        self::assertSame('apply', $applies['rows'][2]['role'] ?? null);
        self::assertSame('Bonus 1', $applies['rows'][2]['title'] ?? null);
        self::assertSame('Bonus', $applies['rows'][2]['typeLabel'] ?? null);
        self::assertSame('apply_type0', $applies['rows'][2]['type']['key'] ?? null);
        self::assertSame('apply_value0', $applies['rows'][2]['value']['key'] ?? null);
    }

    public function testSubtypeJsonMapIsSortedByTranslatedLabel(): void
    {
        $profile = GameProfile::load(BASE_DIR . '/game');
        $fields = new ProtoFormFields(new Translator(BASE_DIR . '/lang', 'en', 'en'), new ProtoEnums($profile));
        $map = $fields->subtypesJsonMap();
        $labels = array_column($map['ITEM_WEAPON'] ?? [], 'label');
        $sorted = $labels;
        usort($sorted, [SelectOptions::class, 'compare']);

        self::assertSame($sorted, $labels);
        self::assertNotSame([], $labels);
    }

    /**
     * @param list<array<string, mixed>> $tabs
     * @return array<string, mixed>
     */
    private function field(array $tabs, string $key): array
    {
        foreach ($tabs as $tab) {
            foreach ($tab['fields'] ?? [] as $field) {
                if (($field['key'] ?? '') === $key) {
                    return $field;
                }
            }
        }

        self::fail('Missing proto field ' . $key);
    }
}
