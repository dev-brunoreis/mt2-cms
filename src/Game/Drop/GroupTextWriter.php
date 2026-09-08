<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Drop;

class GroupTextWriter
{
    /**
     * @param list<array{name: string, attrs: array<string, list<string>>, items: list<list<string>>}> $groups
     */
    public function write(array $groups): string
    {
        $blocks = [];

        foreach ($groups as $group) {
            $lines = ['Group	' . $group['name'], '{'];

            foreach ($group['attrs'] as $key => $values) {
                $lines[] = "\t" . $key . "\t" . implode("\t", $values);
            }

            $index = 1;

            foreach ($group['items'] as $item) {
                $lines[] = "\t" . $index . "\t" . implode("\t", $item);
                $index++;
            }

            $lines[] = '}';
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks) . ($blocks !== [] ? "\n" : '');
    }
}
