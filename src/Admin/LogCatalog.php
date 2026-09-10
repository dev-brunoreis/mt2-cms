<?php

declare(strict_types=1);

namespace Mt2Cms\Admin;

class LogCatalog
{
    public const CONNECTIONS_ID = 'connections';

    public const GROUP_CONNECTIONS = 'connections';
    public const GROUP_SECURITY = 'security';
    public const GROUP_ECONOMY = 'economy';
    public const GROUP_PROGRESSION = 'progression';
    public const GROUP_COMMANDS = 'commands';
    public const GROUP_SESSIONS = 'sessions';
    public const GROUP_OTHER = 'other';

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function groupDefinitions(): array
    {
        return [
            ['id' => self::GROUP_CONNECTIONS, 'label' => 'admin.logs.groups.connections'],
            ['id' => self::GROUP_SECURITY, 'label' => 'admin.logs.groups.security'],
            ['id' => self::GROUP_ECONOMY, 'label' => 'admin.logs.groups.economy'],
            ['id' => self::GROUP_PROGRESSION, 'label' => 'admin.logs.groups.progression'],
            ['id' => self::GROUP_COMMANDS, 'label' => 'admin.logs.groups.commands'],
            ['id' => self::GROUP_SESSIONS, 'label' => 'admin.logs.groups.sessions'],
            ['id' => self::GROUP_OTHER, 'label' => 'admin.logs.groups.other'],
        ];
    }

    /**
     * @return list<array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            self::table('bootlog', self::GROUP_OTHER, ['time', 'hostname', 'channel'], ['hostname', 'channel'], 'time'),
            self::table('change_name', self::GROUP_PROGRESSION, ['pid', 'old_name', 'new_name', 'time', 'ip'], ['old_name', 'new_name', 'ip', 'pid'], 'time', ['pid']),
            self::table(
                'command_log',
                self::GROUP_COMMANDS,
                ['id', 'userid', 'server', 'ip', 'port', 'username', 'command', 'date'],
                ['username', 'command', 'ip', 'userid'],
                'date',
                ['username'],
            ),
            self::table(
                'cube',
                self::GROUP_ECONOMY,
                ['id', 'pid', 'time', 'x', 'y', 'item_vnum', 'item_uid', 'item_count', 'success'],
                ['pid', 'item_vnum', 'item_uid'],
                'time',
                ['pid'],
                ['item_uid'],
            ),
            self::table('dragon_slay_log', self::GROUP_PROGRESSION, ['guild_id', 'vnum', 'start_time', 'end_time'], ['guild_id', 'vnum'], 'start_time'),
            self::table(
                'fish_log',
                self::GROUP_ECONOMY,
                ['time', 'player_id', 'map_index', 'fish_id', 'fishing_level', 'waiting_time', 'success', 'size'],
                ['player_id', 'fish_id'],
                'time',
                ['player_id'],
            ),
            self::table('goldlog', self::GROUP_ECONOMY, ['date', 'time', 'pid', 'what', 'how', 'hint'], ['pid', 'how', 'hint', 'what'], 'date', ['pid']),
            self::table(
                'hack_crc_log',
                self::GROUP_SECURITY,
                ['time', 'login', 'name', 'ip', 'server', 'why', 'crc'],
                ['login', 'name', 'ip', 'server', 'why'],
                'time',
                ['name'],
            ),
            self::table('hack_log', self::GROUP_SECURITY, ['time', 'login', 'name', 'ip', 'server', 'why'], ['login', 'name', 'ip', 'server', 'why'], 'time', ['name']),
            self::table(
                'hackshield_log',
                self::GROUP_SECURITY,
                ['pid', 'login', 'account_id', 'name', 'time', 'reason'],
                ['login', 'name', 'reason', 'account_id', 'pid'],
                'time',
                ['pid'],
            ),
            self::table(
                'levellog',
                self::GROUP_PROGRESSION,
                ['name', 'level', 'time', 'playtime', 'account_id', 'pid'],
                ['name', 'account_id', 'pid'],
                'time',
                ['pid'],
            ),
            self::table(
                'log',
                self::GROUP_OTHER,
                ['type', 'time', 'who', 'x', 'y', 'what', 'how', 'hint', 'ip', 'vnum'],
                ['type', 'how', 'hint', 'ip', 'who', 'what', 'vnum'],
                'time',
                ['who'],
                ['what'],
            ),
            self::table(
                'loginlog',
                self::GROUP_SESSIONS,
                ['type', 'time', 'channel', 'account_id', 'pid', 'level', 'job', 'playtime'],
                ['type', 'account_id', 'pid'],
                'time',
                ['pid'],
            ),
            self::table(
                'loginlog2',
                self::GROUP_SESSIONS,
                ['id', 'type', 'is_gm', 'login_time', 'channel', 'account_id', 'pid', 'client_version', 'ip', 'logout_time', 'playtime'],
                ['type', 'ip', 'client_version', 'account_id', 'pid'],
                'login_time',
                ['pid'],
            ),
            self::table('money_log', self::GROUP_ECONOMY, ['time', 'type', 'vnum', 'gold'], ['type', 'vnum'], 'time'),
            self::table(
                'pcbang_loginlog',
                self::GROUP_SESSIONS,
                ['id', 'time', 'pcbang_id', 'ip', 'pid', 'play_time'],
                ['ip', 'pid', 'pcbang_id'],
                'time',
                ['pid'],
            ),
            self::table(
                'playercount',
                self::GROUP_SESSIONS,
                ['date', 'count_red', 'count_yellow', 'count_blue', 'count_total'],
                [],
                'date',
            ),
            self::table(
                'quest_reward_log',
                self::GROUP_PROGRESSION,
                ['quest_name', 'player_id', 'player_level', 'reward_type', 'reward_value1', 'reward_value2', 'time'],
                ['quest_name', 'reward_type', 'player_id'],
                'time',
                ['player_id'],
            ),
            self::table(
                'refinelog',
                self::GROUP_ECONOMY,
                ['pid', 'item_name', 'item_id', 'step', 'time', 'is_success', 'setType'],
                ['item_name', 'step', 'pid', 'item_id'],
                'time',
                ['pid'],
                ['item_id'],
            ),
            self::table('shout_log', self::GROUP_OTHER, ['time', 'channel', 'empire', 'shout'], ['shout', 'channel', 'empire'], 'time'),
            self::table('speed_hack', self::GROUP_SECURITY, ['pid', 'time', 'x', 'y', 'hack_count'], ['pid', 'hack_count'], 'time', ['pid']),
        ];
    }

    /**
     * @return list<string>
     */
    public static function tabIds(): array
    {
        $ids = [self::CONNECTIONS_ID];

        foreach (self::all() as $log) {
            $ids[] = $log['id'];
        }

        return $ids;
    }

    /**
     * @return list<array{id: string, label: string, tabs: list<array{id: string, label: string}>}>
     */
    public static function groupedTabs(): array
    {
        $groups = [];

        foreach (self::groupDefinitions() as $group) {
            $groups[$group['id']] = [
                'id' => $group['id'],
                'label' => $group['label'],
                'tabs' => [],
            ];
        }

        $groups[self::GROUP_CONNECTIONS]['tabs'][] = [
            'id' => self::CONNECTIONS_ID,
            'label' => 'admin.nav.logs.connections',
        ];

        foreach (self::all() as $log) {
            $groupId = $log['group'];

            if (!isset($groups[$groupId])) {
                continue;
            }

            $groups[$groupId]['tabs'][] = [
                'id' => $log['id'],
                'label' => $log['label'],
            ];
        }

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['tabs'] !== [],
        ));
    }

    /**
     * @return list<array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * }>
     */
    public static function forCharacter(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $log): bool => $log['playerColumns'] !== [],
        ));
    }

    /**
     * @return list<array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * }>
     */
    public static function forItem(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $log): bool => $log['itemColumns'] !== [],
        ));
    }

    /**
     * @return array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
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
     * @param list<string> $columns
     * @param list<string> $search
     * @param list<string> $playerColumns
     * @param list<string> $itemColumns
     * @return array{
     *   id: string,
     *   table: string,
     *   label: string,
     *   group: string,
     *   columns: list<string>,
     *   search: list<string>,
     *   dateColumn: string|null,
     *   playerColumns: list<string>,
     *   itemColumns: list<string>
     * }
     */
    private static function table(
        string $id,
        string $group,
        array $columns,
        array $search,
        ?string $dateColumn,
        array $playerColumns = [],
        array $itemColumns = [],
    ): array {
        return [
            'id' => $id,
            'table' => $id,
            'label' => 'admin.nav.logs.' . $id,
            'group' => $group,
            'columns' => $columns,
            'search' => $search,
            'dateColumn' => $dateColumn,
            'playerColumns' => $playerColumns,
            'itemColumns' => $itemColumns,
        ];
    }
}
