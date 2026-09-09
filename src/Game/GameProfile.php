<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

use Mt2Cms\Model\Env;

class GameProfile
{
    public const KIND_ITEM = 'item';
    public const KIND_MOB = 'mob';

    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, array<string, mixed>> */
    private array $schemas = [];

    private function __construct(
        private readonly string $gameDir,
    ) {
        $this->config = $this->loadJson($this->gameDir . '/config.json', 'game config');
        $this->schemas[self::KIND_ITEM] = $this->loadJson($this->gameDir . '/schema/item.json', 'item schema');
        $this->schemas[self::KIND_MOB] = $this->loadJson($this->gameDir . '/schema/mob.json', 'mob schema');
    }

    public static function load(?string $gameDir = null): self
    {
        $dir = self::resolveGameDir($gameDir);

        if (!isset(self::$cache[$dir])) {
            self::$cache[$dir] = new self($dir);
        }

        return self::$cache[$dir];
    }

    public function gameDir(): string
    {
        return $this->gameDir;
    }

    public function locale(): string
    {
        return (string) ($this->config['locale'] ?? 'en');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(string $kind): array
    {
        $kind = $this->normalizeKind($kind);

        return $this->schemas[$kind];
    }

    /**
     * @return list<string>
     */
    public function columns(string $kind): array
    {
        return $this->stringList($this->schema($kind)['columns'] ?? [], $kind . ' columns');
    }

    /**
     * @return list<string>
     */
    public function listColumns(string $kind): array
    {
        return $this->stringList($this->schema($kind)['list_columns'] ?? [], $kind . ' list_columns');
    }

    /**
     * @return list<array{id: string, fields: list<string>}>
     */
    public function formTabs(string $kind): array
    {
        $tabs = $this->schema($kind)['form_tabs'] ?? [];

        if (!is_array($tabs)) {
            throw new \RuntimeException('Invalid form_tabs in ' . $kind . ' schema.');
        }

        $out = [];

        foreach ($tabs as $tab) {
            if (!is_array($tab) || !isset($tab['id'], $tab['fields']) || !is_array($tab['fields'])) {
                throw new \RuntimeException('Invalid form tab in ' . $kind . ' schema.');
            }

            $out[] = [
                'id' => (string) $tab['id'],
                'fields' => $this->stringList($tab['fields'], $kind . ' form tab ' . $tab['id']),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function defaults(string $kind): array
    {
        $kind = $this->normalizeKind($kind);
        $defaults = $this->schema($kind)['defaults'] ?? [];

        if (!is_array($defaults)) {
            throw new \RuntimeException('Invalid defaults in ' . $kind . ' schema.');
        }

        $record = [];

        foreach ($this->columns($kind) as $key) {
            $record[$key] = isset($defaults[$key]) ? (string) $defaults[$key] : '';
        }

        $record['locale_name'] = '';

        return $record;
    }

    /**
     * @return list<string>
     */
    public function enumList(string $kind, string $key): array
    {
        $value = $this->schema($kind)[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        return $this->stringList($value, $kind . '.' . $key);
    }

    /**
     * @return array<string, list<string>>
     */
    public function subtypesByType(): array
    {
        $subtypes = $this->schema(self::KIND_ITEM)['subtypes'] ?? [];

        if (!is_array($subtypes)) {
            throw new \RuntimeException('Invalid subtypes in item schema.');
        }

        $out = [];

        foreach ($subtypes as $type => $list) {
            if (!is_array($list)) {
                throw new \RuntimeException('Invalid subtype list for ' . $type . '.');
            }

            $out[(string) $type] = $this->stringList($list, 'subtypes.' . $type);
        }

        return $out;
    }

    /**
     * @return array<string, array{separator: string, tokens: list<string>}>
     */
    public function bitmaskFields(string $kind): array
    {
        $raw = $this->schema($kind)['bitmask_fields'] ?? [];
        $out = [];

        if (!is_array($raw)) {
            return $out;
        }

        foreach ($raw as $field => $config) {
            if (!is_array($config)) {
                continue;
            }

            $tokens = $config['tokens'] ?? [];

            if (is_string($tokens)) {
                $tokens = $this->enumList($kind, $tokens);
            } elseif (is_array($tokens)) {
                $tokens = $this->stringList($tokens, $kind . '.bitmask.' . $field);
            } else {
                $tokens = [];
            }

            $out[(string) $field] = [
                'separator' => (string) ($config['separator'] ?? '|'),
                'tokens' => $tokens,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function valueLabelsFor(string $itemType, string $subtype = ''): array
    {
        $labels = $this->schema(self::KIND_ITEM)['value_labels'] ?? [];

        if (!is_array($labels)) {
            return $this->defaultValueLabels();
        }

        if ($subtype !== '' && isset($labels[$itemType . ':' . $subtype]) && is_array($labels[$itemType . ':' . $subtype])) {
            return $this->valueLabelMap($labels[$itemType . ':' . $subtype]);
        }

        if (isset($labels[$itemType]) && is_array($labels[$itemType])) {
            return $this->valueLabelMap($labels[$itemType]);
        }

        if (isset($labels['_default']) && is_array($labels['_default'])) {
            return $this->valueLabelMap($labels['_default']);
        }

        return $this->defaultValueLabels();
    }

    public function path(string $key): string
    {
        $relative = $this->config['paths'][$key] ?? null;

        if (!is_string($relative) || $relative === '') {
            throw new \RuntimeException('Missing game path: ' . $key);
        }

        return $this->resolvePath($relative);
    }

    public function optionalPath(string $key): ?string
    {
        $relative = $this->config['paths'][$key] ?? null;

        if (!is_string($relative) || $relative === '') {
            return null;
        }

        return $this->resolvePath($relative);
    }

    public function dropPath(string $key): string
    {
        $dropsDir = $this->path('drops');
        $filename = $this->config['drops'][$key] ?? null;

        if (!is_string($filename) || $filename === '') {
            throw new \RuntimeException('Missing drop file: ' . $key);
        }

        return $dropsDir . '/' . ltrim($filename, '/');
    }

    public function faceFilename(int $job): ?string
    {
        $faces = $this->config['faces'] ?? [];

        if (!is_array($faces)) {
            return null;
        }

        $name = $faces[(string) $job] ?? $faces[$job] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return list<string>
     */
    public function commonRanks(): array
    {
        return $this->enumList(self::KIND_MOB, 'common_ranks');
    }

    private static function resolveGameDir(?string $gameDir): string
    {
        if ($gameDir !== null && $gameDir !== '') {
            return rtrim($gameDir, '/');
        }

        $envDir = Env::getInstance()->get('GAME_DIR');

        if (is_string($envDir) && $envDir !== '') {
            if ($envDir[0] === '/') {
                return rtrim($envDir, '/');
            }

            return rtrim(BASE_DIR . '/' . ltrim($envDir, '/'), '/');
        }

        return BASE_DIR . '/game';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path, string $label): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Missing ' . $label . ': ' . $path);
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException('Unable to read ' . $label . ': ' . $path);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid JSON in ' . $label . ': ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid ' . $label . ': expected object.');
        }

        return $decoded;
    }

    private function resolvePath(string $relative): string
    {
        if ($relative[0] === '/') {
            return $relative;
        }

        return $this->gameDir . '/' . ltrim($relative, '/');
    }

    private function normalizeKind(string $kind): string
    {
        if ($kind !== self::KIND_ITEM && $kind !== self::KIND_MOB) {
            throw new \InvalidArgumentException('Unknown proto kind: ' . $kind);
        }

        return $kind;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Invalid ' . $label . ': expected array.');
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /**
     * @param array<string, mixed> $map
     * @return array<string, string>
     */
    private function valueLabelMap(array $map): array
    {
        $out = [];

        foreach ($map as $field => $label) {
            $out[(string) $field] = (string) $label;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function defaultValueLabels(): array
    {
        return [
            'value0' => 'value_0',
            'value1' => 'value_1',
            'value2' => 'value_2',
            'value3' => 'value_3',
            'value4' => 'value_4',
            'value5' => 'value_5',
        ];
    }
}
