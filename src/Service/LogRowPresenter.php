<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Admin\LogCatalog;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\Economy\GoldlogHintParser;
use Mt2Cms\Service\Economy\ItemLogHintParser;

/**
 * Turn raw game-log rows into labels a GM can read.
 */
final class LogRowPresenter
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{
     *   players: list<int>,
     *   vnums: list<int>,
     *   accounts: list<int>,
     *   guilds: list<int>
     * }
     */
    public function neededLookups(string $logId, array $rows): array
    {
        $players = [];
        $vnums = [];
        $accounts = [];
        $guilds = [];
        $log = LogCatalog::get($logId) ?? ['playerColumns' => []];
        $playerColumns = $log['playerColumns'] ?? [];

        foreach ($rows as $row) {
            foreach ($playerColumns as $column) {
                if (in_array($column, ['pid', 'player_id', 'who'], true)) {
                    $id = (int) ($row[$column] ?? 0);

                    if ($id > 0) {
                        $players[$id] = $id;
                    }
                }
            }

            foreach (['item_vnum', 'vnum', 'fish_id'] as $column) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $id = (int) $row[$column];

                if ($id > 0) {
                    $vnums[$id] = $id;
                }
            }

            $accountId = (int) ($row['account_id'] ?? 0);

            if ($accountId > 0) {
                $accounts[$accountId] = $accountId;
            }

            $guildId = (int) ($row['guild_id'] ?? 0);

            if ($guildId > 0) {
                $guilds[$guildId] = $guildId;
            }
        }

        return [
            'players' => array_values($players),
            'vnums' => array_values($vnums),
            'accounts' => array_values($accounts),
            'guilds' => array_values($guilds),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     * @param array<int, string> $accountLogins
     * @param array<int, string> $guildNames
     * @return list<array<string, mixed>>
     */
    public function decorate(
        string $logId,
        array $rows,
        array $playerNames = [],
        array $itemNames = [],
        array $accountLogins = [],
        array $guildNames = [],
    ): array {
        $out = [];

        foreach ($rows as $row) {
            $out[] = $this->decorateRow($logId, $row, $playerNames, $itemNames, $accountLogins, $guildNames);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     * @param array<int, string> $accountLogins
     * @param array<int, string> $guildNames
     * @return array<string, mixed>
     */
    private function decorateRow(
        string $logId,
        array $row,
        array $playerNames,
        array $itemNames,
        array $accountLogins,
        array $guildNames,
    ): array {
        foreach (['pid', 'player_id', 'who'] as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $id = (int) $row[$column];
            $row[$column . '_name'] = $id > 0 ? ($playerNames[$id] ?? '') : '';
        }

        foreach (['item_vnum', 'vnum', 'fish_id'] as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $id = (int) $row[$column];
            $row[$column . '_name'] = $id > 0 ? ($itemNames[$id] ?? '') : '';
        }

        if (array_key_exists('account_id', $row)) {
            $id = (int) $row['account_id'];
            $row['account_login'] = $row['account_login'] ?? ($id > 0 ? ($accountLogins[$id] ?? '') : '');
        }

        if (array_key_exists('guild_id', $row)) {
            $id = (int) $row['guild_id'];
            $row['guild_name'] = $id > 0 ? ($guildNames[$id] ?? '') : '';
        }

        if (array_key_exists('how', $row)) {
            $row['_how_label'] = $this->codeLabel('how', (string) $row['how']);
        }

        if (array_key_exists('type', $row)) {
            $row['_type_label'] = $this->codeLabel('type', (string) $row['type']);
        }

        $shop = ItemLogHintParser::parseShop((string) ($row['hint'] ?? ''));

        if ($shop !== null) {
            $row['_yang'] = $shop['yang'];
            $row['_shop'] = $shop;
        }

        if ($logId === 'goldlog' && array_key_exists('what', $row)) {
            $row['_yang'] = (int) $row['what'];
        }

        if ($logId === 'money_log' && array_key_exists('gold', $row)) {
            $row['_yang'] = (int) $row['gold'];
        }

        $row['_summary'] = $this->summarize($logId, $row, $playerNames, $itemNames, $accountLogins, $guildNames);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     * @param array<int, string> $accountLogins
     * @param array<int, string> $guildNames
     */
    private function summarize(
        string $logId,
        array $row,
        array $playerNames,
        array $itemNames,
        array $accountLogins,
        array $guildNames,
    ): string {
        return match ($logId) {
            'goldlog' => $this->goldlogSummary($row, $playerNames),
            'log' => $this->itemCharacterSummary($row, $playerNames, $itemNames),
            'money_log' => $this->moneySummary($row, $itemNames),
            'command_log' => $this->commandSummary($row),
            'levellog' => $this->levelSummary($row),
            'refinelog' => $this->refineSummary($row, $playerNames),
            'cube' => $this->cubeSummary($row, $playerNames, $itemNames),
            'loginlog', 'loginlog2' => $this->loginSummary($row, $playerNames),
            'hack_log', 'hack_crc_log', 'hackshield_log' => $this->hackSummary($row),
            'change_name' => $this->changeNameSummary($row),
            'fish_log' => $this->fishSummary($row, $playerNames, $itemNames),
            'quest_reward_log' => $this->questSummary($row, $playerNames),
            'dragon_slay_log' => $this->dragonSummary($row, $guildNames, $itemNames),
            'shout_log' => $this->shoutSummary($row),
            'speed_hack' => $this->speedHackSummary($row, $playerNames),
            'bootlog' => $this->bootSummary($row),
            'pcbang_loginlog' => $this->t('admin.logs.summary.pcbang', [
                'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
                'ip' => (string) ($row['ip'] ?? ''),
            ]),
            'playercount' => $this->t('admin.logs.summary.playercount', [
                'total' => (string) (int) ($row['count_total'] ?? 0),
            ]),
            'connections' => $this->t('admin.logs.summary.connections', [
                'account' => $this->accountLabel($row, $accountLogins),
                'ip' => (string) ($row['ip'] ?? ''),
                'count' => (string) (int) ($row['connections'] ?? 0),
            ]),
            default => $this->fallbackSummary($row),
        };
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     */
    private function goldlogSummary(array $row, array $playerNames): string
    {
        $how = $this->primaryHow((string) ($row['how'] ?? ''));
        $parsed = GoldlogHintParser::parse((string) ($row['hint'] ?? ''));
        $item = $parsed['name'] ?? trim((string) ($row['hint'] ?? ''));
        $count = $parsed['count'] ?? 1;
        $yang = abs((int) ($row['what'] ?? 0));
        $key = match ($how) {
            'SHOP_SELL' => 'admin.logs.summary.goldlog_shop_sell',
            'SHOP_BUY' => 'admin.logs.summary.goldlog_shop_buy',
            'SELL' => 'admin.logs.summary.goldlog_npc_sell',
            'BUY' => 'admin.logs.summary.goldlog_npc_buy',
            'EXCHANGE_GIVE' => 'admin.logs.summary.goldlog_trade_give',
            'EXCHANGE_TAKE' => 'admin.logs.summary.goldlog_trade_take',
            'QUEST' => 'admin.logs.summary.goldlog_quest',
            default => 'admin.logs.summary.goldlog_generic',
        };

        return $this->t($key, [
            'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
            'item' => $item !== '' ? $item : $this->t('admin.logs.unknown_item'),
            'count' => (string) $count,
            'yang' => $this->yang($yang),
            'how' => $row['_how_label'] ?? $this->codeLabel('how', $how),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     */
    private function itemCharacterSummary(array $row, array $playerNames, array $itemNames): string
    {
        $how = strtoupper(trim((string) ($row['how'] ?? '')));
        $shop = is_array($row['_shop'] ?? null) ? $row['_shop'] : ItemLogHintParser::parseShop((string) ($row['hint'] ?? ''));

        if (is_array($shop) && ($how === 'SHOP_SELL' || $how === 'SHOP_BUY')) {
            $other = trim((string) ($shop['other_name'] ?? ''));

            if ($other === '') {
                $other = '#' . (int) ($shop['other_pid'] ?? 0);
            }

            return $this->t(
                $how === 'SHOP_SELL' ? 'admin.logs.summary.log_shop_sell' : 'admin.logs.summary.log_shop_buy',
                [
                    'player' => $this->playerName((int) ($row['who'] ?? 0), $playerNames),
                    'item' => $this->itemName(
                        (int) ($row['vnum'] ?? 0),
                        $itemNames,
                        (string) ($shop['name'] ?? $row['hint'] ?? ''),
                    ),
                    'count' => (string) (int) ($shop['count'] ?? 1),
                    'yang' => $this->yang((int) ($shop['yang'] ?? 0)),
                    'other' => $other,
                ],
            );
        }

        $type = strtoupper((string) ($row['type'] ?? 'ITEM'));
        $item = $this->itemName((int) ($row['vnum'] ?? 0), $itemNames, (string) ($row['hint'] ?? ''));
        $key = $type === 'CHARACTER'
            ? 'admin.logs.summary.log_character'
            : 'admin.logs.summary.log_item';

        return $this->t($key, [
            'player' => $this->playerName((int) ($row['who'] ?? 0), $playerNames),
            'how' => $row['_how_label'] ?? $this->codeLabel('how', (string) ($row['how'] ?? '')),
            'item' => $item,
            'uid' => (string) (int) ($row['what'] ?? 0),
            'x' => (string) (int) ($row['x'] ?? 0),
            'y' => (string) (int) ($row['y'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $itemNames
     */
    private function moneySummary(array $row, array $itemNames): string
    {
        $type = strtoupper((string) ($row['type'] ?? ''));
        $gold = (int) ($row['gold'] ?? 0);
        $vnum = (int) ($row['vnum'] ?? 0);
        $name = $this->itemName($vnum, $itemNames, $vnum > 0 ? '#' . $vnum : '');

        return $this->t('admin.logs.summary.money', [
            'type' => $row['_type_label'] ?? $this->codeLabel('type', $type),
            'yang' => $this->yang(abs($gold)),
            'sign' => $gold < 0 ? '−' : ($gold > 0 ? '+' : ''),
            'name' => $name,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function commandSummary(array $row): string
    {
        return $this->t('admin.logs.summary.command', [
            'user' => (string) ($row['username'] ?? ''),
            'command' => (string) ($row['command'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function levelSummary(array $row): string
    {
        return $this->t('admin.logs.summary.level', [
            'player' => (string) ($row['name'] ?? ''),
            'level' => (string) (int) ($row['level'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     */
    private function refineSummary(array $row, array $playerNames): string
    {
        $ok = (int) ($row['is_success'] ?? 0) === 1;

        return $this->t($ok ? 'admin.logs.summary.refine_ok' : 'admin.logs.summary.refine_fail', [
            'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
            'item' => (string) ($row['item_name'] ?? ''),
            'step' => (string) ($row['step'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     */
    private function cubeSummary(array $row, array $playerNames, array $itemNames): string
    {
        $ok = (int) ($row['success'] ?? 0) === 1;

        return $this->t($ok ? 'admin.logs.summary.cube_ok' : 'admin.logs.summary.cube_fail', [
            'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
            'item' => $this->itemName((int) ($row['item_vnum'] ?? 0), $itemNames),
            'count' => (string) (int) ($row['item_count'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     */
    private function loginSummary(array $row, array $playerNames): string
    {
        $type = strtoupper((string) ($row['type'] ?? 'LOGIN'));
        $key = str_contains($type, 'OUT')
            ? 'admin.logs.summary.logout'
            : 'admin.logs.summary.login';

        return $this->t($key, [
            'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
            'channel' => (string) (int) ($row['channel'] ?? 0),
            'level' => (string) (int) ($row['level'] ?? 0),
            'ip' => (string) ($row['ip'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hackSummary(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $login = trim((string) ($row['login'] ?? ''));
        $who = $name !== '' ? $name : ($login !== '' ? $login : '#' . (int) ($row['pid'] ?? 0));

        return $this->t('admin.logs.summary.hack', [
            'player' => $who,
            'reason' => (string) ($row['why'] ?? $row['reason'] ?? ''),
            'ip' => (string) ($row['ip'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function changeNameSummary(array $row): string
    {
        return $this->t('admin.logs.summary.rename', [
            'old' => (string) ($row['old_name'] ?? ''),
            'new' => (string) ($row['new_name'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     * @param array<int, string> $itemNames
     */
    private function fishSummary(array $row, array $playerNames, array $itemNames): string
    {
        $ok = (int) ($row['success'] ?? 0) === 1;

        return $this->t($ok ? 'admin.logs.summary.fish_ok' : 'admin.logs.summary.fish_fail', [
            'player' => $this->playerName((int) ($row['player_id'] ?? 0), $playerNames),
            'fish' => $this->itemName((int) ($row['fish_id'] ?? 0), $itemNames, '#' . (int) ($row['fish_id'] ?? 0)),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     */
    private function questSummary(array $row, array $playerNames): string
    {
        return $this->t('admin.logs.summary.quest', [
            'player' => $this->playerName((int) ($row['player_id'] ?? 0), $playerNames),
            'quest' => (string) ($row['quest_name'] ?? ''),
            'reward' => (string) ($row['reward_type'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $guildNames
     * @param array<int, string> $itemNames
     */
    private function dragonSummary(array $row, array $guildNames, array $itemNames): string
    {
        $guildId = (int) ($row['guild_id'] ?? 0);

        return $this->t('admin.logs.summary.dragon', [
            'guild' => $guildNames[$guildId] ?? ('#' . $guildId),
            'mob' => $this->itemName((int) ($row['vnum'] ?? 0), $itemNames, '#' . (int) ($row['vnum'] ?? 0)),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shoutSummary(array $row): string
    {
        return $this->t('admin.logs.summary.shout', [
            'text' => (string) ($row['shout'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $playerNames
     */
    private function speedHackSummary(array $row, array $playerNames): string
    {
        return $this->t('admin.logs.summary.speed_hack', [
            'player' => $this->playerName((int) ($row['pid'] ?? 0), $playerNames),
            'count' => (string) (int) ($row['hack_count'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function bootSummary(array $row): string
    {
        return $this->t('admin.logs.summary.boot', [
            'host' => (string) ($row['hostname'] ?? ''),
            'channel' => (string) (int) ($row['channel'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fallbackSummary(array $row): string
    {
        foreach (['_summary', 'hint', 'command', 'shout', 'why', 'reason', 'item_name'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $this->t('admin.logs.summary.generic');
    }

    /**
     * @param array<int, string> $playerNames
     */
    private function playerName(int $id, array $playerNames): string
    {
        if ($id < 1) {
            return $this->t('admin.logs.unknown_player');
        }

        $name = trim($playerNames[$id] ?? '');

        return $name !== '' ? $name : '#' . $id;
    }

    /**
     * @param array<int, string> $itemNames
     */
    private function itemName(int $vnum, array $itemNames, string $fallback = ''): string
    {
        if ($vnum > 0 && isset($itemNames[$vnum]) && trim($itemNames[$vnum]) !== '') {
            return $itemNames[$vnum];
        }

        $fallback = trim($fallback);

        if ($fallback !== '') {
            return $fallback;
        }

        return $vnum > 0 ? '#' . $vnum : $this->t('admin.logs.unknown_item');
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $accountLogins
     */
    private function accountLabel(array $row, array $accountLogins): string
    {
        $login = trim((string) ($row['account_login'] ?? ''));

        if ($login !== '') {
            return $login;
        }

        $id = (int) ($row['account_id'] ?? 0);

        if ($id > 0 && isset($accountLogins[$id]) && $accountLogins[$id] !== '') {
            return $accountLogins[$id];
        }

        return $id > 0 ? '#' . $id : $this->t('admin.logs.unknown_account');
    }

    private function codeLabel(string $kind, string $raw): string
    {
        $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        $labels = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $key = 'admin.logs.codes.' . $kind . '.' . str_replace([' ', '/'], ['_', '_'], $part);
            $labels[] = $this->translator->has($key) ? $this->t($key) : $this->humanize($part);
        }

        return $labels === [] ? $raw : implode(' · ', $labels);
    }

    private function primaryHow(string $how): string
    {
        $parts = preg_split('/\s*,\s*/', strtoupper(trim($how))) ?: [];

        foreach ($parts as $part) {
            if ($part !== '') {
                return $part;
            }
        }

        return '';
    }

    private function humanize(string $code): string
    {
        $code = str_replace(['_', '-'], ' ', $code);
        $code = strtolower($code);

        return $code === '' ? $code : ucwords($code);
    }

    private function yang(int $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    private function t(string $key, array $replace = []): string
    {
        return $this->translator->get($key, $replace);
    }
}
