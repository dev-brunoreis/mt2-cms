<?php

namespace Mt2Cms;

use Mt2Cms\Model\Env;

class Application
{
    public function __construct()
    {
        $this->loadConfigs();
    }

    public function run()
    {
    }

    public static function getEnv()
    {
        return Env::getInstance();
    }

    public static function loadConfigs()
    {
        Env::load();
    }
}
