<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Icon;

class TgaDecoder
{
    /**
     * @return array{width: int, height: int, rgba: string}|null
     */
    public function decode(string $data): ?array
    {
        if (strlen($data) < 18) {
            return null;
        }

        $idLength = ord($data[0]);
        $colorMapType = ord($data[1]);
        $imageType = ord($data[2]);
        $width = unpack('v', substr($data, 12, 2))[1];
        $height = unpack('v', substr($data, 14, 2))[1];
        $depth = ord($data[16]);
        $descriptor = ord($data[17]);

        if ($width < 1 || $height < 1 || $width > 512 || $height > 512) {
            return null;
        }

        if (!in_array($imageType, [2, 10], true) || !in_array($depth, [24, 32], true)) {
            return null;
        }

        $offset = 18 + $idLength;

        if ($colorMapType === 1) {
            $mapLength = unpack('v', substr($data, 5, 2))[1];
            $mapDepth = ord($data[7]);
            $offset += $mapLength * intdiv($mapDepth + 7, 8);
        }

        if ($offset > strlen($data)) {
            return null;
        }

        $bytesPerPixel = intdiv($depth, 8);
        $pixelCount = $width * $height;
        $pixels = $imageType === 10
            ? $this->readRle(substr($data, $offset), $bytesPerPixel, $pixelCount)
            : $this->readRaw(substr($data, $offset), $bytesPerPixel, $pixelCount);

        if ($pixels === null) {
            return null;
        }

        $rgba = $this->toRgba($pixels, $bytesPerPixel, $width, $height, $descriptor);

        return [
            'width' => $width,
            'height' => $height,
            'rgba' => $rgba,
        ];
    }

    private function readRaw(string $payload, int $bpp, int $count): ?string
    {
        $need = $count * $bpp;

        if (strlen($payload) < $need) {
            return null;
        }

        return substr($payload, 0, $need);
    }

    private function readRle(string $payload, int $bpp, int $count): ?string
    {
        $out = '';
        $offset = 0;
        $length = strlen($payload);
        $written = 0;

        while ($written < $count) {
            if ($offset >= $length) {
                return null;
            }

            $header = ord($payload[$offset]);
            $offset++;
            $run = ($header & 0x7F) + 1;

            if ($written + $run > $count) {
                return null;
            }

            if (($header & 0x80) !== 0) {
                if ($offset + $bpp > $length) {
                    return null;
                }

                $pixel = substr($payload, $offset, $bpp);
                $offset += $bpp;
                $out .= str_repeat($pixel, $run);
            } else {
                $bytes = $run * $bpp;

                if ($offset + $bytes > $length) {
                    return null;
                }

                $out .= substr($payload, $offset, $bytes);
                $offset += $bytes;
            }

            $written += $run;
        }

        return $out;
    }

    private function toRgba(string $pixels, int $bpp, int $width, int $height, int $descriptor): string
    {
        $topOrigin = ($descriptor & 0x20) !== 0;
        $rightOrigin = ($descriptor & 0x10) !== 0;
        $rgba = '';

        for ($y = 0; $y < $height; $y++) {
            $srcY = $topOrigin ? $y : ($height - 1 - $y);
            $row = '';

            for ($x = 0; $x < $width; $x++) {
                $srcX = $rightOrigin ? ($width - 1 - $x) : $x;
                $i = ($srcY * $width + $srcX) * $bpp;
                $b = ord($pixels[$i]);
                $g = ord($pixels[$i + 1]);
                $r = ord($pixels[$i + 2]);
                $a = $bpp === 4 ? ord($pixels[$i + 3]) : 255;
                $row .= chr($r) . chr($g) . chr($b) . chr($a);
            }

            $rgba .= $row;
        }

        return $rgba;
    }
}
