<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Icon;

class PngEncoder
{
    public function encode(int $width, int $height, string $rgba): string
    {
        $raw = '';
        $stride = $width * 4;

        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00" . substr($rgba, $y * $stride, $stride);
        }

        return "\x89PNG\r\n\x1a\n"
            . $this->chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            . $this->chunk('IDAT', zlib_encode($raw, ZLIB_ENCODING_DEFLATE, 6))
            . $this->chunk('IEND', '');
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
