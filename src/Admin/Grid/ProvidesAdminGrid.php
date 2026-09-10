<?php

declare(strict_types=1);

namespace Mt2Cms\Admin\Grid;

interface ProvidesAdminGrid
{
    public function gridDefinition(): GridDefinition;
}
