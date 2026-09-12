<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Game\Proto;

use Mt2Cms\Game\Proto\TabProtoTable;
use PHPUnit\Framework\TestCase;

final class TabProtoTableTest extends TestCase
{
    /** @var list<string> */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
        }
    }

    public function testRangeVnumIsListedAsStartVnumWithLocaleName(): void
    {
        $table = $this->table(
            [
                "VNUM\tNAME\tTYPE",
                "10\tSword\tITEM_WEAPON",
                "110000~110099\tGem\tITEM_DS",
            ],
            [
                "VNUM\tLOCALE_NAME",
                "10\tSword+0",
                "110000~110099\tRough Dragon Diamond",
            ],
        )['table'];

        $rows = $table->all();

        self::assertCount(2, $rows);
        self::assertSame('10', $rows[0]['vnum']);
        self::assertSame('Sword+0', $rows[0]['locale_name']);
        self::assertSame('110000', $rows[1]['vnum']);
        self::assertSame('110000~110099', $rows[1]['vnum_token']);
        self::assertSame('Rough Dragon Diamond', $rows[1]['locale_name']);
    }

    public function testFindMatchesVnumInsideRange(): void
    {
        $table = $this->table(
            ["VNUM\tNAME\tTYPE", "110000~110099\tGem\tITEM_DS"],
            ["VNUM\tLOCALE_NAME", "110000~110099\tRough Dragon Diamond"],
        )['table'];

        $row = $table->find(110050);

        self::assertNotNull($row);
        self::assertSame('110000', $row['vnum']);
        self::assertTrue($table->exists(110000));
        self::assertTrue($table->exists(110099));
        self::assertFalse($table->exists(110100));
    }

    public function testUpdatePreservesRangeTokenInProtoAndNames(): void
    {
        $files = $this->table(
            ["VNUM\tNAME\tTYPE", "110000~110099\tGem\tITEM_DS"],
            ["VNUM\tLOCALE_NAME", "110000~110099\tRough Dragon Diamond"],
        );

        $files['table']->update(110050, [
            'locale_name' => 'Polished Dragon Diamond',
            'type' => 'ITEM_DS',
            'name' => 'Gem',
        ]);

        $proto = (string) file_get_contents($files['proto']);
        $names = (string) file_get_contents($files['names']);

        self::assertStringContainsString("110000~110099\tGem\tITEM_DS", $proto);
        self::assertStringContainsString("110000~110099\tPolished Dragon Diamond", $names);
        self::assertStringNotContainsString("\n110000\t", $proto);
        self::assertSame('Polished Dragon Diamond', $files['table']->find(110000)['locale_name'] ?? null);
    }

    public function testCreateWritesSingleVnum(): void
    {
        $files = $this->table(
            ["VNUM\tNAME\tTYPE", "10\tSword\tITEM_WEAPON"],
            ["VNUM\tLOCALE_NAME", "10\tSword+0"],
        );

        $files['table']->create([
            'vnum' => '20',
            'name' => 'Dagger',
            'type' => 'ITEM_WEAPON',
            'locale_name' => 'Dagger+0',
        ]);

        $proto = (string) file_get_contents($files['proto']);

        self::assertStringContainsString("20\tDagger\tITEM_WEAPON", $proto);
        self::assertSame('Dagger+0', $files['table']->find(20)['locale_name'] ?? null);
    }

    /**
     * @param list<string> $protoLines
     * @param list<string> $nameLines
     * @return array{table: TabProtoTable, proto: string, names: string}
     */
    private function table(array $protoLines, array $nameLines): array
    {
        $dir = sys_get_temp_dir() . '/mt2-proto-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700);
        $this->tmpDirs[] = $dir;

        $protoPath = $dir . '/item_proto.txt';
        $namesPath = $dir . '/item_names.txt';
        file_put_contents($protoPath, implode("\n", $protoLines) . "\n");
        file_put_contents($namesPath, implode("\n", $nameLines) . "\n");

        return [
            'table' => new TabProtoTable($protoPath, $namesPath, ['vnum', 'name', 'type']),
            'proto' => $protoPath,
            'names' => $namesPath,
        ];
    }
}
