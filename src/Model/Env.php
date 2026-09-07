<?php

declare(strict_types=1);

namespace Mt2Cms\Model;

class Env
{
    public static $instance;

    /** @var array<string, string|null> */
    protected $values = [];

    protected function __construct()
    {
    }

    public static function load()
    {
        self::getInstance()->values = \Dotenv\Dotenv::createArrayBacked(BASE_DIR)->safeLoad();
        \Dotenv\Dotenv::createImmutable(BASE_DIR)->safeLoad();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->load();
        }

        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        return $default;
    }

    public function getAll()
    {
        return $this->values;
    }
}
