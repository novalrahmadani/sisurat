<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SignaturePng implements ValidationRule
{
    private const MAX_BYTES = 307200;

    private const PNG_HEADER = "\x89PNG\r\n\x1a\n";

    private ?string $canonical = null;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->canonical = is_string($value) ? $this->normalize($value) : null;
        if ($this->canonical === null) {
            $fail('Tanda tangan kosong atau tidak valid. Hapus, lalu gambar kembali pada kotak tanda tangan.');
        }
    }

    public function canonical(): ?string
    {
        return $this->canonical;
    }

    private function normalize(string $value): ?string
    {
        if (! str_starts_with($value, 'data:image/png;base64,')) {
            return null;
        }
        $encoded = substr($value, 22);
        if (strlen($encoded) > 409600 || preg_match('/^[A-Za-z0-9+\/]+={0,2}$/D', $encoded) !== 1) {
            return null;
        }
        $png = base64_decode($encoded, true);
        if ($png === false || strlen($png) > self::MAX_BYTES || ! str_starts_with($png, self::PNG_HEADER)) {
            return null;
        }

        $offset = 8;
        $header = null;
        $compressed = '';
        $ended = false;
        $hasImageData = false;
        $imageDataEnded = false;
        while ($offset + 12 <= strlen($png)) {
            $length = unpack('Nlength', substr($png, $offset, 4))['length'];
            if ($length > self::MAX_BYTES || $offset + 12 + $length > strlen($png)) {
                return null;
            }
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $checksum = substr($png, $offset + 8 + $length, 4);
            if (! hash_equals(hash('crc32b', $type.$data, true), $checksum)) {
                return null;
            }
            if ($header === null && $type !== 'IHDR') {
                return null;
            }
            if ($type === 'IHDR') {
                if ($header !== null || $length !== 13) {
                    return null;
                }
                $header = $data;
            } elseif ($type === 'IDAT') {
                if ($imageDataEnded) {
                    return null;
                }
                $hasImageData = true;
                $compressed .= $data;
            } elseif ($type === 'IEND') {
                if ($length !== 0 || ! $hasImageData || $offset + 12 !== strlen($png)) {
                    return null;
                }
                $ended = true;
                break;
            } else {
                if ($hasImageData) {
                    $imageDataEnded = true;
                }
                if (preg_match('/^[A-Za-z]{4}$/D', $type) !== 1 || ctype_upper($type[0])) {
                    return null;
                }
            }
            $offset += 12 + $length;
        }
        if (! $ended || $header === null) {
            return null;
        }

        $dimensions = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $header);
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        if ($width < 16 || $height < 16 || $width > 2048 || $height > 2048
            || $width * $height > 2097152 || $dimensions['depth'] !== 8
            || ! in_array($dimensions['color'], [2, 6], true)
            || $dimensions['compression'] !== 0 || $dimensions['filter'] !== 0 || $dimensions['interlace'] !== 0) {
            return null;
        }

        $channels = $dimensions['color'] === 6 ? 4 : 3;
        $rowLength = $width * $channels;
        $expectedLength = ($rowLength + 1) * $height;
        $pixels = @zlib_decode($compressed, $expectedLength);
        if ($pixels === false || strlen($pixels) !== $expectedLength) {
            return null;
        }

        $previous = str_repeat("\0", $rowLength);
        $normalized = '';
        $inkPixels = 0;
        for ($y = 0; $y < $height; $y++) {
            $rowOffset = $y * ($rowLength + 1);
            $filter = ord($pixels[$rowOffset]);
            if ($filter > 4) {
                return null;
            }
            $row = substr($pixels, $rowOffset + 1, $rowLength);
            for ($x = 0; $x < $rowLength; $x++) {
                $left = $x >= $channels ? ord($row[$x - $channels]) : 0;
                $up = ord($previous[$x]);
                $upperLeft = $x >= $channels ? ord($previous[$x - $channels]) : 0;
                $predictor = match ($filter) {
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => $this->paeth($left, $up, $upperLeft),
                    default => 0,
                };
                $row[$x] = chr((ord($row[$x]) + $predictor) & 255);
            }
            for ($x = 0; $x < $rowLength; $x += $channels) {
                $alpha = $channels === 4 ? ord($row[$x + 3]) : 255;
                if ($alpha > 32 && ord($row[$x]) + ord($row[$x + 1]) + ord($row[$x + 2]) < 720) {
                    $inkPixels++;
                }
            }
            $normalized .= "\0".$row;
            $previous = $row;
        }
        if ($inkPixels < 20) {
            return null;
        }

        $compressed = gzcompress($normalized, 6);
        if ($compressed === false) {
            return null;
        }
        $canonical = self::PNG_HEADER.$this->chunk('IHDR', $header)
            .$this->chunk('IDAT', $compressed).$this->chunk('IEND', '');

        return strlen($canonical) <= self::MAX_BYTES ? base64_encode($canonical) : null;
    }

    private function paeth(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $cornerDistance = abs($estimate - $upperLeft);

        return $leftDistance <= $upDistance && $leftDistance <= $cornerDistance
            ? $left
            : ($upDistance <= $cornerDistance ? $up : $upperLeft);
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    }
}
