<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Admin\Grid\GridUrl;
use Mt2Cms\Game\Display;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Support\HtmlSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function __construct(
        private Display $display,
        private Translator $translator,
        private ?GameIconService $icons = null,
        private ?HtmlSanitizer $htmlSanitizer = null,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('job_name', [$this->display, 'job']),
            new TwigFilter('skill_name', [$this->display, 'skill']),
            new TwigFilter('empire_name', [$this->display, 'empire']),
            new TwigFilter('playtime', [$this->display, 'playtime']),
            new TwigFilter('duration', [$this->display, 'duration']),
            new TwigFilter('unix_date', [$this->display, 'unixDate']),
            new TwigFilter('game_date', [$this->display, 'datetime']),
            new TwigFilter('news_html', function (mixed $html): string {
                $sanitizer = $this->htmlSanitizer ?? new HtmlSanitizer();

                return $sanitizer->sanitize((string) $html);
            }, ['is_safe' => ['html']]),
            new TwigFilter('ticket_html', function (mixed $html): string {
                $sanitizer = $this->htmlSanitizer ?? new HtmlSanitizer();

                return $sanitizer->ticketHtml((string) $html);
            }, ['is_safe' => ['html']]),
            new TwigFilter('news_excerpt', function (mixed $html, int $max = 160): string {
                $sanitizer = $this->htmlSanitizer ?? new HtmlSanitizer();

                return $sanitizer->excerpt((string) $html, $max);
            }),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', [$this->translator, 'get']),
            new TwigFunction('item_icon', function (mixed $vnum): ?string {
                return $this->icons?->itemUrl($vnum);
            }),
            new TwigFunction('face_icon', function (mixed $job): ?string {
                return $this->icons?->faceUrl($job);
            }),
            new TwigFunction('grid_url', function (array $grid, array $overrides = []): string {
                return GridUrl::fromGrid($grid, $overrides);
            }),
            new TwigFunction('grid_sort_url', function (array $grid, string $column): string {
                return GridUrl::sortFromGrid($grid, $column);
            }),
        ];
    }
}
