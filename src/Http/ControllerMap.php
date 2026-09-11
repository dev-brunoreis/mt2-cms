<?php

declare(strict_types=1);

namespace Mt2Cms\Http;

trait ControllerMap
{
    private function resolveController(string $class): object
    {
        static $factories = null;

        if ($factories === null) {
            /** @var array<class-string, callable(\Mt2Cms\Application): object> $factories */
            $factories = require __DIR__ . '/controller_factories.php';
        }

        if (!isset($factories[$class])) {
            throw new \RuntimeException('Unknown controller: ' . $class);
        }

        return $factories[$class]($this);
    }
}
