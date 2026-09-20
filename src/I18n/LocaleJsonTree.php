<?php

declare(strict_types=1);

namespace Mt2Cms\I18n;

/**
 * Flatten / apply helpers for nested locale JSON (used by bin/i18n-deepl.php and tests).
 */
final class LocaleJsonTree
{
    /**
     * @param array<string, mixed> $node
     * @return list<array{path: string, text: string}>
     */
    public static function flatten(array $node, bool $skipAdmin = false, string $prefix = ''): array
    {
        $out = [];

        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if ($skipAdmin && $prefix === '' && $key === 'admin') {
                continue;
            }

            if (is_array($value)) {
                if (self::isPluralMap($value)) {
                    foreach ($value as $form => $text) {
                        if (is_string($text)) {
                            $out[] = ['path' => $path . '.' . $form, 'text' => $text];
                        }
                    }
                    continue;
                }

                foreach (self::flatten($value, false, $path) as $leaf) {
                    $out[] = $leaf;
                }
                continue;
            }

            if (is_string($value)) {
                $out[] = ['path' => $path, 'text' => $value];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, string> $translations path => text
     * @return array<string, mixed>
     */
    public static function apply(array $template, array $translations): array
    {
        $tree = $template;

        foreach ($translations as $path => $text) {
            self::setPath($tree, $path, $text);
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $node
     * @return list<string>
     */
    public static function leafPaths(array $node): array
    {
        return array_map(static fn (array $leaf): string => $leaf['path'], self::flatten($node));
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function countLeaves(array $node): int
    {
        return count(self::flatten($node));
    }

    /**
     * @param array<string, mixed> $node
     * @return list<string>
     */
    public static function placeholdersInTree(array $node): array
    {
        $found = [];

        foreach (self::flatten($node) as $leaf) {
            if (preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $leaf['text'], $m) > 0) {
                foreach ($m[0] as $ph) {
                    $found[] = $leaf['path'] . '=' . $ph;
                }
            }
        }

        sort($found);

        return $found;
    }

    public static function protectPlaceholders(string $text): string
    {
        $parts = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if ($parts === false) {
            return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }

        $out = '';

        foreach ($parts as $part) {
            if ($part !== '' && preg_match('/^\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $part) === 1) {
                $out .= '<x>' . $part . '</x>';
                continue;
            }

            $out .= htmlspecialchars($part, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }

    public static function unprotectPlaceholders(string $text): string
    {
        $text = (string) preg_replace('/<\/?x>/', '', $text);
        $text = html_entity_decode($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $text = (string) preg_replace('/\{\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+\}/', '{$1}', $text);

        return $text;
    }

    /**
     * @return list<string>
     */
    public static function placeholdersIn(string $text): array
    {
        if (preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $text, $m) === false) {
            return [];
        }

        $list = $m[0];
        sort($list);

        return $list;
    }

    /**
     * Keep source when translation is empty or drops/changes placeholders.
     */
    public static function sanitizeTranslation(string $source, string $translated): string
    {
        $translated = trim($translated);

        if ($translated === '') {
            return $source;
        }

        $srcPh = self::placeholdersIn($source);
        $dstPh = self::placeholdersIn($translated);

        if ($srcPh !== $dstPh) {
            return $source;
        }

        return $translated;
    }

    /**
     * @param array<mixed> $value
     */
    public static function isPluralMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        $allowed = ['zero', 'one', 'two', 'few', 'many', 'other'];

        foreach (array_keys($value) as $k) {
            if (!in_array((string) $k, $allowed, true)) {
                return false;
            }
        }

        return isset($value['one']) || isset($value['other']);
    }

    /**
     * @param array<string, mixed> $tree
     */
    private static function setPath(array &$tree, string $path, string $value): void
    {
        $parts = explode('.', $path);
        $ref = &$tree;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;

                return;
            }

            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }

            $ref = &$ref[$part];
        }
    }
}
