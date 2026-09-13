<?php

declare(strict_types=1);

namespace Mt2Cms\Service;

use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\GameEconomyScanRepository;
use Mt2Cms\Repository\GuildRepository;
use Mt2Cms\Repository\PlayerRepository;

final class LogEnricher
{
    public function __construct(
        private LogRowPresenter $presenter,
        private PlayerRepository $players,
        private GameEconomyScanRepository $scan,
        private AccountRepository $accounts,
        private GuildRepository $guilds,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function decorate(string $logId, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $need = $this->presenter->neededLookups($logId, $rows);

        return $this->presenter->decorate(
            $logId,
            $rows,
            $this->players->namesByIds($need['players']),
            $this->scan->itemNamesByVnum($need['vnums']),
            $this->accounts->loginsByIds($need['accounts']),
            $this->guilds->namesByIds($need['guilds']),
        );
    }
}
