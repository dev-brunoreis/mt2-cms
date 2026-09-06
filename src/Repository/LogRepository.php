<?php

namespace Mt2Cms\Repository;

class LogRepository extends Repository
{
    protected function database(): string
    {
        return 'log';
    }

    public function recentLogins(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        return $this->revealAll(
            $this->db()->fetchAll(
                'SELECT type, time, channel, account_id, pid, level, job, playtime
                 FROM `loginlog` ORDER BY time DESC LIMIT ?',
                [$limit],
            ),
        );
    }
}
