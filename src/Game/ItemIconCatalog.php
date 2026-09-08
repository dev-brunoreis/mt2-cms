<?php

declare(strict_types=1);

namespace Mt2Cms\Game;

class ItemIconCatalog
{
    /** @var array<int, string>|null */
    private ?array $byVnum = null;

    public function __construct(
        private string $path,
    ) {
    }

    public function filename(int $vnum): ?string
    {
        if ($vnum < 1) {
            return null;
        }

        $this->load();

        return $this->byVnum[$vnum] ?? null;
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

                $vnum = (int) $parts[0];
                $filename = self::tgaBasename((string) $parts[2]);

                if ($vnum > 0 && $filename !== null) {
                    $this->byVnum[$vnum] = $filename;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function tgaBasename(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $name = basename($path);

        if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]+\.tga$/i', $name)) {
            return null;
        }

        return $name;
    }
}
