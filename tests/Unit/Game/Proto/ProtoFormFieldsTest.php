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
