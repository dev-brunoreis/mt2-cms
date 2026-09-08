<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

use Mt2Cms\Game\Drop\LocaleText;

class ItemDescCatalog
{
    /** @var array<int, string>|null */
    private ?array $byVnum = null;

    public function __construct(
        private string $path,
    ) {
    }

    public function description(int $vnum): string
    {
        if ($vnum < 1) {
            return '';
        }

        $this->load();

        return $this->byVnum[$vnum] ?? '';
    }

    private function load(): void
    {
        if ($this->byVnum !== null) {
            return;
        }

        $this->byVnum = [];

        if ($this->path === '' || !is_file($this->path)) {
            return;
        }

        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode("\t", $line);

                if (count($parts) < 3) {
                    continue;
                }

                $vnum = (int) array_shift($parts);
                array_shift($parts);
                $description = LocaleText::decode(trim(implode("\t", $parts)));

                if ($vnum > 0 && $description !== '') {
                    $this->byVnum[$vnum] = $description;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
