<?php

namespace Mt2Cms;

use Mt2Cms\Model\Env;

class Application
{
    public function __construct()
    {
        Env::load();
    }

    public function run()
    {
       echo 'working';
    }

    public static function getEnv()
    {
        return Env::getInstance();
    }
}
