<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Drop;

class GroupTextParser
{
    /**
     * @return list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}>
     */
    public function parse(string $contents): array
    {
        $groups = [];
        $current = null;

        foreach (explode("\n", str_replace("\r\n", "\n", $contents)) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if (preg_match('/^Group\s+(.+)$/i', $trimmed, $match) === 1) {
                if ($current !== null) {
                    $groups[] = $current;
                }

                $current = [
                    'name' => trim($match[1], " \t\""),
                    'attrs' => [],
                    'items' => [],
                ];

                continue;
            }

            if ($trimmed === '{') {
                continue;
            }

            if ($trimmed === '}') {
                if ($current !== null) {
                    $groups[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($current === null) {
                continue;
            }

            $tokens = $this->tokenize($trimmed);

            if ($tokens === []) {
                continue;
            }

            $key = array_shift($tokens);

            if (ctype_digit($key)) {
                $current['items'][] = $tokens;

                continue;
            }

            $current['attrs'][strtolower($key)] = $tokens;
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $line): array
    {
        if (preg_match_all('/"([^"]*)"|([^\s]+)/u', $line, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $tokens = [];

        foreach ($matches as $match) {
            $quoted = $match[1] ?? '';
            $tokens[] = $quoted !== '' ? $quoted : ($match[2] ?? '');
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }
}
