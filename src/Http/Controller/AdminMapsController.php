<?php

declare(strict_types=1);

namespace Mt2Cms\Http\Controller;

use Mt2Cms\Auth\AdminAuth;
use Mt2Cms\Auth\Auth;
use Mt2Cms\Auth\Csrf;
use Mt2Cms\Game\Map\MapCatalog;
use Mt2Cms\Http\Response;
use Mt2Cms\I18n\Translator;
use Mt2Cms\Repository\PlayerRepository;
use Mt2Cms\Service\GameAtlasService;
use Mt2Cms\Theme\ThemeEngine;

class AdminMapsController extends AdminController
{
    public function __construct(
        ThemeEngine $theme,
        Auth $auth,
        Csrf $csrf,
        Translator $translator,
        AdminAuth $adminAuth,
        ThemeEngine $adminTheme,
        private MapCatalog $maps,
        private GameAtlasService $atlas,
        private PlayerRepository $players,
    ) {
        parent::__construct($theme, $auth, $csrf, $translator, $adminAuth, $adminTheme);
    }

    public function index(): Response
    {
        $list = $this->mapList();
        $requested = (int) ($_GET['map'] ?? 0);
        $selected = $this->maps->find($requested);

        if ($selected === null) {
            $selected = $this->defaultMap($list);
        }

        $payload = $selected !== null ? $this->payload($selected) : [
            'map' => null,
            'players' => [],
        ];

        return $this->adminView('maps', 'pages/maps.twig', [
            'title' => $this->t('admin.maps.title'),
            'pageLead' => $this->t('admin.maps.lead'),
            'maps' => $list,
            'selected' => $selected,
            'payload' => $payload,
        ]);
    }

    public function atlas(string $id): Response
    {
        if ($guard = $this->denyUnlessAdmin()) {
            return $guard;
        }

        $png = $this->atlas->png((int) $id);

        if ($png === null) {
            return new Response('', 404, ['Content-Type' => 'image/png']);
        }

        return Response::png($png)->withHeader('Cache-Control', 'private, max-age=86400');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapList(): array
    {
        $counts = $this->players->countByMap();
        $out = [];

        foreach ($this->maps->all() as $map) {
            $index = (int) $map['index'];
            $map['label'] = $this->mapLabel($map);
            $map['online'] = $counts[$index] ?? 0;
            $out[] = $map;
        }

        usort($out, static function (array $a, array $b): int {
            $atlas = ((int) $b['has_atlas']) <=> ((int) $a['has_atlas']);

            if ($atlas !== 0) {
                return $atlas;
            }

            $online = ((int) $b['online']) <=> ((int) $a['online']);

            return $online !== 0 ? $online : ((int) $a['index'] <=> (int) $b['index']);
        });

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $list
     * @return array<string, mixed>|null
     */
    private function defaultMap(array $list): ?array
    {
        foreach ($list as $map) {
            if (!empty($map['has_atlas'])) {
                return $this->maps->find((int) $map['index']);
            }
        }

        return $list[0] ?? null;
    }

    /**
     * @param array<string, mixed> $map
     * @return array{map: array<string, mixed>, players: list<array<string, mixed>>}
     */
    private function payload(array $map): array
    {
        $index = (int) $map['index'];
        $rows = $this->players->listOnMap($index);
        $players = [];

        foreach ($rows as $row) {
            $point = $this->maps->pointOnAtlas($map, (int) ($row['x'] ?? 0), (int) ($row['y'] ?? 0));
            $players[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'level' => (int) ($row['level'] ?? 0),
                'job' => (int) ($row['job'] ?? 0),
                'empire' => (int) ($row['empire'] ?? 0),
                'x' => (int) ($row['x'] ?? 0),
                'y' => (int) ($row['y'] ?? 0),
                'left' => $point['left'] ?? null,
                'top' => $point['top'] ?? null,
                'href' => '/admin/characters/' . (int) $row['id'],
            ];
        }

        return [
            'map' => [
                'index' => $index,
                'folder' => (string) $map['folder'],
                'label' => $this->mapLabel($map),
                'has_atlas' => (bool) $map['has_atlas'],
                'online' => count($players),
            ],
            'players' => $players,
        ];
    }

    /**
     * @param array<string, mixed> $map
     */
    private function mapLabel(array $map): string
    {
        $key = 'admin.maps.names.' . $map['folder'];

        if ($this->translator->has($key)) {
            return $this->t($key);
        }

        return (string) $map['folder'];
    }
}
