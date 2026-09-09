<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Icon;

class DdsDecoder
{
    /**
     * @return array{width: int, height: int, rgba: string}|null
     */
    public function decode(string $data): ?array
    {
        if (strlen($data) < 128 || substr($data, 0, 4) !== 'DDS ') {
            return null;
        }

        $height = unpack('V', substr($data, 12, 4))[1];
        $width = unpack('V', substr($data, 16, 4))[1];
        $fourCc = substr($data, 84, 4);

        if ($width < 1 || $height < 1 || $width > 1024 || $height > 1024) {
            return null;
        }

        $payload = substr($data, 128);
        $rgba = match ($fourCc) {
            'DXT1' => $this->decodeBc($payload, $width, $height, 8, false),
            'DXT3' => $this->decodeBc($payload, $width, $height, 16, true),
            'DXT5' => $this->decodeDxt5($payload, $width, $height),
            default => null,
        };

        if ($rgba === null) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'rgba' => $rgba,
        ];
    }

    private function decodeBc(string $payload, int $width, int $height, int $blockSize, bool $explicitAlpha): ?string
    {
        $blocksX = intdiv($width + 3, 4);
        $blocksY = intdiv($height + 3, 4);
        $need = $blocksX * $blocksY * $blockSize;

        if (strlen($payload) < $need) {
            return null;
        }

        $out = array_fill(0, $width * $height * 4, "\0");
        $offset = 0;

        for ($by = 0; $by < $blocksY; $by++) {
            for ($bx = 0; $bx < $blocksX; $bx++) {
                $block = substr($payload, $offset, $blockSize);
                $offset += $blockSize;

                if ($explicitAlpha) {
                    $alphas = $this->dxt3Alphas($block);
                    $colors = $this->dxtColors(substr($block, 8, 8), false);
                } else {
                    $colors = $this->dxtColors($block, true);
                    $alphas = null;
                }

                $this->writeBlock($out, $width, $height, $bx * 4, $by * 4, $colors, $alphas);
            }
        }

        return implode('', $out);
    }

    private function decodeDxt5(string $payload, int $width, int $height): ?string
    {
        $blocksX = intdiv($width + 3, 4);
        $blocksY = intdiv($height + 3, 4);
        $need = $blocksX * $blocksY * 16;

        if (strlen($payload) < $need) {
            return null;
        }

        $out = array_fill(0, $width * $height * 4, "\0");
        $offset = 0;

        for ($by = 0; $by < $blocksY; $by++) {
            for ($bx = 0; $bx < $blocksX; $bx++) {
                $block = substr($payload, $offset, 16);
                $offset += 16;
                $alphas = $this->dxt5Alphas($block);
                $colors = $this->dxtColors(substr($block, 8, 8), false);
                $this->writeBlock($out, $width, $height, $bx * 4, $by * 4, $colors, $alphas);
            }
        }

        return implode('', $out);
    }

    /**
     * @param list<string> $out
     * @param list<array{0:int,1:int,2:int,3:int}> $colors
     * @param list<int>|null $alphas
     */
    private function writeBlock(array &$out, int $width, int $height, int $x0, int $y0, array $colors, ?array $alphas): void
    {
        for ($py = 0; $py < 4; $py++) {
            $y = $y0 + $py;

            if ($y >= $height) {
                continue;
            }

            for ($px = 0; $px < 4; $px++) {
                $x = $x0 + $px;

                if ($x >= $width) {
                    continue;
                }

                $i = $py * 4 + $px;
                $c = $colors[$i];
                $a = $alphas[$i] ?? $c[3];
                $o = ($y * $width + $x) * 4;
                $out[$o] = chr($c[0]);
                $out[$o + 1] = chr($c[1]);
                $out[$o + 2] = chr($c[2]);
                $out[$o + 3] = chr($a);
            }
        }
    }

    /**
     * @return list<array{0:int,1:int,2:int,3:int}>
     */
    private function dxtColors(string $block, bool $allowPunchThrough): array
    {
        $c0 = unpack('v', substr($block, 0, 2))[1];
        $c1 = unpack('v', substr($block, 2, 2))[1];
        $lookup = unpack('V', substr($block, 4, 4))[1];
        $palette = [$this->rgb565($c0), $this->rgb565($c1)];

        if (!$allowPunchThrough || $c0 > $c1) {
            $palette[] = $this->mixRgb($palette[0], $palette[1], 2, 1);
            $palette[] = $this->mixRgb($palette[0], $palette[1], 1, 2);
        } else {
            $palette[] = $this->mixRgb($palette[0], $palette[1], 1, 1);
            $palette[] = [0, 0, 0, 0];
        }

        $pixels = [];

        for ($i = 0; $i < 16; $i++) {
            $pixels[] = $palette[($lookup >> ($i * 2)) & 3];
        }

        return $pixels;
    }

    /**
     * @return list<int>
     */
    private function dxt3Alphas(string $block): array
    {
        $alphas = [];

        for ($i = 0; $i < 8; $i++) {
            $byte = ord($block[$i]);
            $alphas[] = ($byte & 0xF) * 17;
            $alphas[] = (($byte >> 4) & 0xF) * 17;
        }

        return $alphas;
    }

    /**
     * @return list<int>
     */
    private function dxt5Alphas(string $block): array
    {
        $a0 = ord($block[0]);
        $a1 = ord($block[1]);
        $bits = unpack('P', substr($block, 2, 6) . "\0\0")[1];
        $palette = [$a0, $a1];

        if ($a0 > $a1) {
            for ($i = 1; $i <= 6; $i++) {
                $palette[] = intdiv((7 - $i) * $a0 + $i * $a1, 7);
            }
        } else {
            for ($i = 1; $i <= 4; $i++) {
                $palette[] = intdiv((5 - $i) * $a0 + $i * $a1, 5);
            }

            $palette[] = 0;
            $palette[] = 255;
        }

        $alphas = [];

        for ($i = 0; $i < 16; $i++) {
            $alphas[] = $palette[($bits >> ($i * 3)) & 7];
        }

        return $alphas;
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function rgb565(int $value): array
    {
        $r = (($value >> 11) & 31) * 255 / 31;
        $g = (($value >> 5) & 63) * 255 / 63;
        $b = ($value & 31) * 255 / 31;

        return [(int) round($r), (int) round($g), (int) round($b), 255];
    }

    /**
     * @param array{0:int,1:int,2:int,3:int} $a
     * @param array{0:int,1:int,2:int,3:int} $b
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function mixRgb(array $a, array $b, int $wa, int $wb): array
    {
        $d = $wa + $wb;

        return [
            intdiv($a[0] * $wa + $b[0] * $wb, $d),
            intdiv($a[1] * $wa + $b[1] * $wb, $d),
            intdiv($a[2] * $wa + $b[2] * $wb, $d),
            255,
        ];
    }
}
