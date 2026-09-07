<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Game\Display;
use Mt2Cms\I18n\Translator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function __construct(
        private Display $display,
        private Translator $translator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('job_name', [$this->display, 'job']),
            new TwigFilter('empire_name', [$this->display, 'empire']),
            new TwigFilter('playtime', [$this->display, 'playtime']),
            new TwigFilter('game_date', [$this->display, 'datetime']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', [$this->translator, 'get']),
        ];
    }
}
