<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Game\Display;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('job_name', [Display::class, 'job']),
            new TwigFilter('empire_name', [Display::class, 'empire']),
            new TwigFilter('playtime', [Display::class, 'playtime']),
            new TwigFilter('game_date', [Display::class, 'datetime']),
        ];
    }
}
