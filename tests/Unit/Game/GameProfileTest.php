<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Game;

use Mt2Cms\Game\GameProfile;
use PHPUnit\Framework\TestCase;

final class GameProfileTest extends TestCase
{
    public function testLoadDoesNotRequireProtoOrClientDumps(): void
    {
        $dir = sys_get_temp_dir() . '/mt2cms-game-profile-' . bin2hex(random_bytes(4));
        mkdir($dir . '/schema', 0777, true);
        copy(BASE_DIR . '/game/config.json', $dir . '/config.json');
        copy(BASE_DIR . '/game/schema/item.json', $dir . '/schema/item.json');
        copy(BASE_DIR . '/game/schema/mob.json', $dir . '/schema/mob.json');

        $profile = GameProfile::load($dir);

        self::assertSame('en', $profile->locale());
        self::assertNotSame([], $profile->columns(GameProfile::KIND_ITEM));
        self::assertSame($dir . '/db/item_proto.txt', $profile->path('item_proto'));
        self::assertSame($dir . '/client/itemdesc.txt', $profile->path('itemdesc'));
        self::assertFileDoesNotExist($profile->path('item_proto'));
        self::assertFileDoesNotExist($profile->path('itemdesc'));
    }
}
