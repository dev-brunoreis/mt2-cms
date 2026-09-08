<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class LogCatalog
{
    public const CONNECTIONS_ID = 'connections';

    /**
     * @return list<array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null
     * }>
     */
    public static function all(): array
    {
        return [
            self::table('bootlog', ['time', 'hostname', 'channel'], ['hostname', 'channel'], 'time'),
            self::table('change_name', ['pid', 'old_name', 'new_name', 'time', 'ip'], ['old_name', 'new_name', 'ip', 'pid'], 'time'),
            self::table(
                'command_log',
                ['id', 'userid', 'server', 'ip', 'port', 'username', 'command', 'date'],
                ['username', 'command', 'ip', 'userid'],
                'date',
            ),
            self::table(
                'cube',
                ['id', 'pid', 'time', 'x', 'y', 'item_vnum', 'item_uid', 'item_count', 'success'],
                ['pid', 'item_vnum', 'item_uid'],
                'time',
            ),
            self::table('dragon_slay_log', ['guild_id', 'vnum', 'start_time', 'end_time'], ['guild_id', 'vnum'], 'start_time'),
            self::table(
                'fish_log',
                ['time', 'player_id', 'map_index', 'fish_id', 'fishing_level', 'waiting_time', 'success', 'size'],
                ['player_id', 'fish_id'],
                'time',
            ),
            self::table('goldlog', ['date', 'time', 'pid', 'what', 'how', 'hint'], ['pid', 'how', 'hint', 'what'], 'date'),
            self::table(
                'hack_crc_log',
                ['time', 'login', 'name', 'ip', 'server', 'why', 'crc'],
                ['login', 'name', 'ip', 'server', 'why'],
                'time',
            ),
            self::table('hack_log', ['time', 'login', 'name', 'ip', 'server', 'why'], ['login', 'name', 'ip', 'server', 'why'], 'time'),
            self::table(
                'hackshield_log',
                ['pid', 'login', 'account_id', 'name', 'time', 'reason'],
                ['login', 'name', 'reason', 'account_id', 'pid'],
                'time',
            ),
            self::table(
                'levellog',
                ['name', 'level', 'time', 'playtime', 'account_id', 'pid'],
                ['name', 'account_id', 'pid'],
                'time',
            ),
            self::table(
                'log',
                ['type', 'time', 'who', 'x', 'y', 'what', 'how', 'hint', 'ip', 'vnum'],
                ['type', 'how', 'hint', 'ip', 'who', 'what', 'vnum'],
                'time',
            ),
            self::table(
                'loginlog',
                ['type', 'time', 'channel', 'account_id', 'pid', 'level', 'job', 'playtime'],
                ['type', 'account_id', 'pid'],
                'time',
            ),
            self::table(
                'loginlog2',
                ['id', 'type', 'is_gm', 'login_time', 'channel', 'account_id', 'pid', 'client_version', 'ip', 'logout_time', 'playtime'],
                ['type', 'ip', 'client_version', 'account_id', 'pid'],
                'login_time',
            ),
            self::table('money_log', ['time', 'type', 'vnum', 'gold'], ['type', 'vnum'], 'time'),
            self::table(
                'pcbang_loginlog',
                ['id', 'time', 'pcbang_id', 'ip', 'pid', 'play_time'],
                ['ip', 'pid', 'pcbang_id'],
                'time',
            ),
            self::table(
                'playercount',
                ['date', 'count_red', 'count_yellow', 'count_blue', 'count_total'],
                [],
                'date',
            ),
            self::table(
                'quest_reward_log',
                ['quest_name', 'player_id', 'player_level', 'reward_type', 'reward_value1', 'reward_value2', 'time'],
                ['quest_name', 'reward_type', 'player_id'],
                'time',
            ),
            self::table(
                'refinelog',
                ['pid', 'item_name', 'item_id', 'step', 'time', 'is_success', 'setType'],
                ['item_name', 'step', 'pid', 'item_id'],
                'time',
            ),
            self::table('shout_log', ['time', 'channel', 'empire', 'shout'], ['shout', 'channel', 'empire'], 'time'),
            self::table('speed_hack', ['pid', 'time', 'x', 'y', 'hack_count'], ['pid', 'hack_count'], 'time'),
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null
     * }|null
     */
    public static function get(string $id): ?array
    {
        foreach (self::all() as $log) {
            if ($log['id'] === $id) {
                return $log;
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, path: string, label: string}>
     */
    public static function navItems(): array
    {
        $items = [
            [
                'id' => 'log-' . self::CONNECTIONS_ID,
                'path' => '/admin/logs/' . self::CONNECTIONS_ID,
                'label' => 'admin.nav.logs.connections',
            ],
        ];

        foreach (self::all() as $log) {
            $items[] = [
                'id' => 'log-' . $log['id'],
                'path' => '/admin/logs/' . $log['id'],
                'label' => $log['label'],
            ];
        }

        return $items;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $search
     * @return array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null
     * }
     */
    private static function table(string $id, array $columns, array $search, ?string $dateColumn): array
    {
        return [
            'id' => $id,
            'table' => $id,
            'label' => 'admin.nav.logs.' . $id,
            'columns' => $columns,
            'search' => $search,
            'dateColumn' => $dateColumn,
        ];
    }
}
