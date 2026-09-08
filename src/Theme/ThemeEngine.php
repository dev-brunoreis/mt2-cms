<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Mt2Cms\Game\Display;
use Mt2Cms\I18n\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ThemeEngine
{
    private Environment $twig;
    private ThemeResolver $resolver;

    /**
     * @param list<array{code: string, name: string}> $locales
     */
    public function __construct(
        string $themesPath,
        string $activeTheme,
        Translator $translator,
        array $locales = [],
    ) {
        $this->resolver = new ThemeResolver($themesPath, $activeTheme);
        $paths = $this->resolver->templatePaths();

        if ($paths === []) {
            throw new \RuntimeException('No theme template paths found');
        }

        $this->twig = new Environment(new FilesystemLoader($paths), [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        $this->twig->addExtension(new TwigExtension(new Display($translator), $translator));
        $this->twig->addGlobal('locale', $translator->locale());
        $this->twig->addGlobal('html_lang', $translator->htmlLang());
        $this->twig->addGlobal('locales', $locales);
        $this->twig->addGlobal('current_path', $this->currentPath());
    }

    private function currentPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
            return '/';
        }

        if (str_contains($uri, "\r") || str_contains($uri, "\n")) {
            return '/';
        }

        return $uri;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $layoutName, array $data = []): string
    {
        $layout = $this->resolver->resolveLayout($layoutName);

        return $this->renderNode($layout, $data);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $data
     */
    private function renderNode(array $node, array $data): string
    {
        $slotHtml = [];
        $slots = $node['slots'] ?? [];

        if (is_array($slots)) {
            foreach ($slots as $slotName => $children) {
                if (!is_array($children)) {
                    continue;
                }

                $parts = [];

                foreach ($children as $child) {
                    if (!is_array($child)) {
                        continue;
                    }

                    $parts[] = $this->renderNode($child, $data);
                }

                $slotHtml[$slotName] = implode('', $parts);
            }
        }

        $template = (string) ($node['template'] ?? '');

        if ($template === '') {
            return implode('', $slotHtml);
        }

        return $this->twig->render($template, array_merge($data, [
            'slots' => $slotHtml,
        ]));
    }

    public function resolver(): ThemeResolver
    {
        return $this->resolver;
    }

    /**
     * @param array<string, mixed> $globals
     */
    public function setGlobals(array $globals): void
    {
        foreach ($globals as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }
    }
}
