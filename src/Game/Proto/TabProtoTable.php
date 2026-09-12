<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

class TabProtoTable
{
    private const MAX_RANGE_SPAN = 10000;

    /**
     * @param list<string> $columns
     */
    public function __construct(
        private string $protoPath,
        private string $namesPath,
        private array $columns,
    ) {
        if ($this->columns === [] || $this->columns[0] !== 'vnum') {
            throw new \InvalidArgumentException('Proto columns must start with vnum');
        }
    }

    /**
     * @return list<array<string, string>>
     */
    public function all(): array
    {
        $names = $this->readNames();
        $rows = [];

        foreach ($this->readProtoRows() as $row) {
            $range = self::parseVnum((string) ($row['vnum'] ?? ''));

            if ($range === null) {
                continue;
            }

            $row['vnum'] = (string) $range['start'];
            $row['vnum_token'] = $range['token'];
            $row['locale_name'] = $names[$range['start']] ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, string>|null
     */
    public function find(int $vnum): ?array
    {
        if ($vnum < 1) {
            return null;
        }

        foreach ($this->all() as $row) {
            if ($this->rowCoversVnum($row, $vnum)) {
                return $row;
            }
        }

        return null;
    }

    public function exists(int $vnum): bool
    {
        return $this->find($vnum) !== null;
    }

    public function protoMtime(): int
    {
        return is_file($this->protoPath) ? (int) filemtime($this->protoPath) : 0;
    }

    public function namesMtime(): int
    {
        return is_file($this->namesPath) ? (int) filemtime($this->namesPath) : 0;
    }

    /**
     * @param array<string, string> $record
     */
    public function create(array $record): void
    {
        $vnum = (int) ($record['vnum'] ?? 0);
        $this->assertVnum($vnum);

        if ($this->exists($vnum)) {
            throw new \RuntimeException('admin.proto.vnum_taken');
        }

        $rows = $this->all();
        $rows[] = $this->normalizeRecord($record);
        $this->writeAll($rows);
    }

    /**
     * @param array<string, string> $record
     */
    public function update(int $vnum, array $record): void
    {
        $this->assertVnum($vnum);
        $found = false;
        $rows = [];

        foreach ($this->all() as $row) {
            if ($this->rowCoversVnum($row, $vnum)) {
                $merged = $this->normalizeRecord(array_merge($row, $record));
                $merged['vnum'] = (string) ((int) ($row['vnum'] ?? $vnum));
                $merged['vnum_token'] = (string) ($row['vnum_token'] ?? $merged['vnum']);
                $merged['name'] = $row['name'];
                $rows[] = $merged;
                $found = true;
            } else {
                $rows[] = $row;
            }
        }

        if (!$found) {
            throw new \RuntimeException('admin.proto.not_found');
        }

        $this->writeAll($rows);
    }

    public function delete(int $vnum): bool
    {
        $this->assertVnum($vnum);
        $rows = [];
        $found = false;

        foreach ($this->all() as $row) {
            if ($this->rowCoversVnum($row, $vnum)) {
                $found = true;

                continue;
            }

            $rows[] = $row;
        }

        if (!$found) {
            return false;
        }

        $this->writeAll($rows);

        return true;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readProtoRows(): array
    {
        $lines = $this->readLines($this->protoPath);
        $header = array_shift($lines);

        if ($header === null) {
            throw new \RuntimeException('admin.proto.missing_files');
        }

        $columnCount = count($this->columns);
        $rows = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $cells = explode("\t", $line);
            $cells = array_pad(array_slice($cells, 0, $columnCount), $columnCount, '');
            $row = [];

            foreach ($this->columns as $index => $key) {
                $row[$key] = $cells[$index];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readNames(): array
    {
        $lines = $this->readLines($this->namesPath);
        array_shift($lines);
        $names = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            [$vnum, $name] = array_pad(explode("\t", $line, 2), 2, '');
            $range = self::parseVnum($vnum);

            if ($range === null) {
                continue;
            }

            $end = $range['end'] - $range['start'] > self::MAX_RANGE_SPAN
                ? $range['start']
                : $range['end'];

            for ($id = $range['start']; $id <= $end; $id++) {
                if (!isset($names[$id])) {
                    $names[$id] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function writeAll(array $rows): void
    {
        $protoHeader = $this->firstLine($this->protoPath);
        $namesHeader = $this->firstLine($this->namesPath);
        $protoLines = [$protoHeader];
        $nameLines = [$namesHeader];

        foreach ($rows as $row) {
            $cells = [];
            $vnumToken = (string) ($row['vnum_token'] ?? $row['vnum'] ?? '');

            foreach ($this->columns as $key) {
                $cells[] = $key === 'vnum' ? $vnumToken : ($row[$key] ?? '');
            }

            $protoLines[] = implode("\t", $cells);
            $nameLines[] = $vnumToken . "\t" . ($row['locale_name'] ?? '');
        }

        $this->writeFile($this->protoPath, $protoLines);
        $this->writeFile($this->namesPath, $nameLines);
    }

    /**
     * @return list<string>
     */
    private function readLines(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('admin.proto.missing_files');
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException('admin.proto.missing_files');
        }

        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        return explode("\n", rtrim($raw, "\n"));
    }

    private function firstLine(string $path): string
    {
        $lines = $this->readLines($path);

        return $lines[0] ?? '';
    }

    /**
     * @param list<string> $lines
     */
    private function writeFile(string $path, array $lines): void
    {
        $dir = dirname($path);

        if (!is_writable($path) && !is_writable($dir)) {
            throw new \RuntimeException('admin.proto.write_failed');
        }

        $payload = implode("\n", $lines) . "\n";
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        $written = file_put_contents($tmp, $payload, LOCK_EX);

        if ($written === false) {
            @unlink($tmp);

            throw new \RuntimeException('admin.proto.write_failed');
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);

            throw new \RuntimeException('admin.proto.write_failed');
        }
    }

    /**
     * @param array<string, string> $record
     * @return array<string, string>
     */
    private function normalizeRecord(array $record): array
    {
        $normalized = [];

        foreach ($this->columns as $key) {
            $normalized[$key] = $this->sanitizeCell((string) ($record[$key] ?? ''));
        }

        $normalized['locale_name'] = $this->sanitizeCell((string) ($record['locale_name'] ?? ''));
        $normalized['vnum_token'] = $this->sanitizeCell((string) ($record['vnum_token'] ?? $normalized['vnum'] ?? ''));

        return $normalized;
    }

    /**
     * Metin2 proto dumps group identical items as `start~end` (e.g. dragon soul gems).
     *
     * @return array{start: int, end: int, token: string}|null
     */
    private static function parseVnum(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d+)~(\d+)$/', $raw, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($start < 1 || $end < $start) {
                return null;
            }

            return [
                'start' => $start,
                'end' => $end,
                'token' => $raw,
            ];
        }

        if (!ctype_digit($raw)) {
            return null;
        }

        $vnum = (int) $raw;

        if ($vnum < 1) {
            return null;
        }

        return [
            'start' => $vnum,
            'end' => $vnum,
            'token' => $raw,
        ];
    }

    /**
     * @param array<string, string> $row
     */
    private function rowCoversVnum(array $row, int $vnum): bool
    {
        $parsed = self::parseVnum((string) ($row['vnum_token'] ?? $row['vnum'] ?? ''));

        if ($parsed === null) {
            return false;
        }

        return $vnum >= $parsed['start'] && $vnum <= $parsed['end'];
    }

    private function sanitizeCell(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);

        return trim($value);
    }

    private function assertVnum(int $vnum): void
    {
        if ($vnum < 1) {
            throw new \InvalidArgumentException('admin.proto.invalid_vnum');
        }
    }
}
