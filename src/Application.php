<?php

namespace Mt2Cms;

use Mt2Cms\Model\Database;
use Mt2Cms\Model\Env;
use Mt2Cms\Repository\AccountRepository;
use Mt2Cms\Repository\PlayerRepository;

class Application
{
    public function __construct()
    {
        Env::load();
    }

    public function run()
    {
        $db = new Database();

        $accounts = new AccountRepository($db);
        $players = new PlayerRepository($db);

        $account = $accounts->findByLogin('admin');
        $characters = $account
            ? $players->findByAccountId((int) $account['id'])
            : [];

        echo '<pre>';
        var_dump($account, $characters);
    }

    public static function getEnv()
    {
        return Env::getInstance();
    }
}
