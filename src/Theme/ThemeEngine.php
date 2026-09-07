<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ThemeEngine
{
    private Environment $twig;
    private ThemeResolver $resolver;

    public function __construct(string $themesPath, string $activeTheme)
    {
        $this->resolver = new ThemeResolver($themesPath, $activeTheme);
        $paths = $this->resolver->templatePaths();

        if ($paths === []) {
            throw new \RuntimeException('No theme template paths found');
        }

        $this->twig = new Environment(new FilesystemLoader($paths), [
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        $this->twig->addExtension(new TwigExtension());
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
}
