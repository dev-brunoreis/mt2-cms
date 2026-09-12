<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Game\Proto;

use Mt2Cms\Game\GameProfile;
use Mt2Cms\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class ProtoTokenI18nTest extends TestCase
{
    private const ITEM_ENUM_KEYS = [
        'types',
        'limit_types',
        'apply_types',
        'anti_flags',
        'item_flags',
        'wear_flags',
        'immune',
    ];

    private const MOB_ENUM_KEYS = [
        'ranks',
        'types',
        'battle_types',
        'sizes',
        'ai_flags',
        'race_flags',
        'immune_flags',
    ];

    public function testEverySchemaEnumAndBitmaskTokenHasEnglishLabel(): void
    {
        $profile = GameProfile::load(BASE_DIR . '/game');
        $translator = new Translator(BASE_DIR . '/lang', 'en', 'en');
        $missing = [];

        foreach ($this->schemaTokens($profile) as $token) {
            $key = 'admin.proto.tokens.' . $token;

            if (!$translator->has($key)) {
                $missing[] = $token;
            }
        }

        self::assertSame(
            [],
            $missing,
            'Missing admin.proto.tokens.* labels for: ' . implode(', ', $missing),
        );
    }

    /**
     * @return list<string>
     */
    private function schemaTokens(GameProfile $profile): array
    {
        $tokens = [];

        foreach (self::ITEM_ENUM_KEYS as $key) {
            foreach ($profile->enumList(GameProfile::KIND_ITEM, $key) as $token) {
                $tokens[$token] = true;
            }
        }

        foreach ($profile->subtypesByType() as $list) {
            foreach ($list as $token) {
                $tokens[$token] = true;
            }
        }

        foreach (self::MOB_ENUM_KEYS as $key) {
            foreach ($profile->enumList(GameProfile::KIND_MOB, $key) as $token) {
                $tokens[$token] = true;
            }
        }

        foreach ([GameProfile::KIND_ITEM, GameProfile::KIND_MOB] as $kind) {
            foreach ($profile->bitmaskFields($kind) as $field) {
                foreach ($field['tokens'] as $token) {
                    $tokens[$token] = true;
                }
            }
        }

        $keys = array_keys($tokens);
        sort($keys);

        return $keys;
    }
}
