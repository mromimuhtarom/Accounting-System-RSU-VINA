<?php

namespace App\Services;

/** Local QR encoder: ISO QR Model 2, version 3, L correction, byte mode, mask 0. */
class AssetQrCode
{
    public function svg(string $text): string
    {
        if (strlen($text) > 53) {
            throw new \InvalidArgumentException('QR payload exceeds version 3-L byte capacity.');
        }
        $bits = '0100'.sprintf('%08b', strlen($text));
        foreach (unpack('C*', $text) as $b) {
            $bits .= sprintf('%08b', $b);
        }
        $bits .= str_repeat('0', min(4, 440 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = [];
        foreach (str_split($bits, 8) as $b) {
            $data[] = bindec($b);
        }
        for ($i = 0; count($data) < 55; $i++) {
            $data[] = $i % 2 === 0 ? 0xEC : 0x11;
        }
        $generator = [1];
        $root = 1;
        for ($i = 0; $i < 15; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= $this->multiply($coef, $root);
            }
            $generator = $next;
            $root = $this->multiply($root, 2);
        }
        $remainder = array_merge($data, array_fill(0, 15, 0));
        foreach ($data as $i => $_) {
            $factor = $remainder[$i];
            foreach ($generator as $j => $coef) {
                $remainder[$i + $j] ^= $this->multiply($coef, $factor);
            }
        }
        $all = array_merge($data, array_slice($remainder, -15));
        $stream = '';
        foreach ($all as $b) {
            $stream .= sprintf('%08b', $b);
        }
        $size = 29;
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = $matrix;
        $set = function ($x, $y, $dark) use (&$matrix, &$reserved, $size) {
            if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                $matrix[$y][$x] = (bool) $dark;
                $reserved[$y][$x] = true;
            }
        };
        foreach ([[3, 3], [25, 3], [3, 25]] as [$cx,$cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $dist = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4);
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $set(22 + $dx, 22 + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
        $format = 0x77C4;
        $bit = fn ($i) => (($format >> $i) & 1) !== 0;
        for ($i = 0; $i <= 5; $i++) {
            $set(8, $i, $bit($i));
        }
        $set(8, 7, $bit(6));
        $set(8, 8, $bit(7));
        $set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $set(8, $size - 15 + $i, $bit($i));
        }
        $set(8, $size - 8, true);
        $index = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vertical = 0; $vertical < $size; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = (($right + 1) & 2) === 0 ? $size - 1 - $vertical : $vertical;
                    if (! $reserved[$y][$x]) {
                        $dark = ($stream[$index] ?? '0') === '1';
                        $index++;
                        $matrix[$y][$x] = ($dark xor (($x + $y) % 2 === 0));
                    }
                }
            }
        }
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 37 37" role="img" aria-label="QR aktiva" shape-rendering="crispEdges"><rect width="37" height="37" fill="white"/><path d="'.$path.'" fill="black"/></svg>';
    }

    private function multiply(int $a, int $b): int
    {
        $result = 0;
        for ($i = 7; $i >= 0; $i--) {
            $result = ($result << 1) ^ (($result >> 7) * 0x11D);
            $result ^= (($b >> $i) & 1) * $a;
        }

        return $result;
    }
}
