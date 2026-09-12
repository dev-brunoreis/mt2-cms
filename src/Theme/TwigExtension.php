<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Admin\AdminPaths;
use Mt2Cms\Admin\Grid\GridUrl;
use Mt2Cms\Game\Display;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Service\GameIconService;
use Mt2Cms\Support\HtmlSanitizer;
use Mt2Cms\Support\SelectOptions;
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
        private ?ThemeResolver $themes = null,
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
            new TwigFilter('proto_token', [$this->display, 'protoToken']),
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
            new TwigFilter('sort_select', function (mixed $options, bool $translate = true): array {
                if (!is_array($options)) {
                    return [];
                }

                $labeled = [];

                foreach ($options as $value => $label) {
                    $text = $translate
                        ? $this->translator->get((string) $label)
                        : (string) $label;
                    $labeled[] = ['value' => $value, 'label' => $label, 'text' => $text];
                }

                usort($labeled, static fn (array $a, array $b): int => SelectOptions::compare(
                    (string) $a['text'],
                    (string) $b['text'],
                ));

                $sorted = [];

                foreach ($labeled as $row) {
                    $sorted[$row['value']] = $row['label'];
                }

                return $sorted;
            }),
            new TwigFilter('sort_by_t', function (mixed $items, string $key = 'label'): array {
                if (!is_array($items)) {
                    return [];
                }

                usort($items, function (mixed $left, mixed $right) use ($key): int {
                    $leftLabel = is_array($left) ? (string) ($left[$key] ?? '') : '';
                    $rightLabel = is_array($right) ? (string) ($right[$key] ?? '') : '';

                    return SelectOptions::compare(
                        $this->translator->get($leftLabel),
                        $this->translator->get($rightLabel),
                    );
                });

                return $items;
            }),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', [$this->translator, 'get']),
            new TwigFunction('theme_asset', function (string $path): string {
                return $this->themes?->resolveAsset($path) ?? '';
            }),
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
            new TwigFunction('admin_path', static function (string $method, mixed ...$args): string {
                if (!method_exists(AdminPaths::class, $method)) {
                    throw new \InvalidArgumentException('Unknown admin path: ' . $method);
                }

                return AdminPaths::{$method}(...$args);
            }),
        ];
    }
}
