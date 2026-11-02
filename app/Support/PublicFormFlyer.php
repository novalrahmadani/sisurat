<?php

namespace App\Support;

use GdImage;
use RuntimeException;

class PublicFormFlyer
{
    public function image(string $qrPng, string $title, string $category, string $url, string $instruction): string
    {
        $image = imagecreatetruecolor(1080, 1350);
        $qr = imagecreatefromstring($qrPng);
        $logo = imagecreatefrompng(resource_path('images/pmb-flyer-logo.png'));
        if ($image === false || $qr === false || $logo === false) {
            throw new RuntimeException('Flyer belum dapat dibuat. Coba kembali.');
        }
        imageantialias($image, true);
        for ($y = 0; $y < 1350; $y++) {
            $ratio = $y / 1349;
            $color = $this->color($image, 0, (int) round(90 - 31 * $ratio), (int) round(54 - 13 * $ratio));
            imageline($image, 0, $y, 1079, $y, $color);
        }
        $white = $this->color($image, 255, 255, 255);
        $gold = $this->color($image, 255, 221, 34);
        $muted = $this->color($image, 211, 234, 222);
        $decoration = imagecolorallocatealpha($image, 255, 255, 255, 119);
        imagefilledellipse($image, 1110, 370, 660, 660, (int) $decoration);
        imagefilledellipse($image, -50, 1150, 590, 590, (int) $decoration);
        imagesetthickness($image, 2);
        imageellipse($image, 1010, 1180, 570, 570, $this->color($image, 28, 109, 77));
        imageellipse($image, 1040, 1180, 650, 650, $this->color($image, 28, 109, 77));

        $this->roundRect($image, 64, 52, 108, 108, 24, $white);
        imagecopyresampled($image, $logo, 70, 58, 0, 0, 96, 96, imagesx($logo), imagesy($logo));
        $this->text($image, 'PMB UNUHA', 199, 105, 30, $white, true);
        $this->text($image, 'Universitas Nurul Huda', 199, 142, 16, $muted);
        $this->roundRect($image, 824, 77, 192, 53, 26, $this->color($image, 24, 113, 75));
        $this->center($image, 'SISURAT', 112, 17, $white, true, 920);
        imageline($image, 64, 190, 1016, 190, $this->color($image, 45, 124, 88));
        $this->center($image, $category, 244, 18, $muted, true);
        $this->center($image, 'Isi Formulir', 350, 66, $white, true);
        $this->center($image, 'Sekarang!', 445, 66, $gold, true);

        $fontSize = 29;
        do {
            $lines = $this->wrap($title, $fontSize, 880);
            if (count($lines) <= 3) {
                break;
            }
            $fontSize--;
        } while ($fontSize > 17);
        if (count($lines) > 3) {
            $lines = array_slice($lines, 0, 3);
            $lines[2] = $this->fit($lines[2].'...', $fontSize, 880);
        }
        $startY = 526 - (int) round((count($lines) - 1) * 37 / 2);
        foreach ($lines as $index => $line) {
            $this->center($image, $line, $startY + $index * 37, $fontSize, $white);
        }

        $this->roundRect($image, 294, 602, 492, 492, 32, $this->color($image, 0, 45, 30));
        $this->roundRect($image, 294, 590, 492, 492, 32, $white);
        imagecopyresized($image, $qr, 326, 622, 0, 0, 428, 428, imagesx($qr), imagesy($qr));
        $this->center($image, 'PINDAI QR DENGAN KAMERA HP', 1137, 20, $white, true);
        $this->center($image, $instruction, 1172, 15, $muted);
        $this->roundRect($image, 100, 1200, 880, 54, 27, $this->color($image, 22, 104, 73));
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? $host : 'SISURAT PMB UNUHA';
        $this->center($image, $this->fit($host, 17, 810), 1236, 17, $white);
        imagefilledrectangle($image, 505, 1283, 575, 1287, $gold);
        $this->center($image, 'SEKRETARIAT PMB UNIVERSITAS NURUL HUDA', 1321, 13, $muted, true);

        ob_start();
        try {
            if (! imagepng($image, null, 6)) {
                throw new RuntimeException('Flyer belum dapat disimpan.');
            }
            $png = ob_get_contents();
            if (! is_string($png)) {
                throw new RuntimeException('Flyer belum dapat disimpan.');
            }

            return $png;
        } finally {
            ob_end_clean();
        }
    }

    private function font(bool $bold = false): string
    {
        return resource_path('fonts/poppins-'.($bold ? 'bold' : 'regular').'.woff');
    }

    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, max(0, min(255, $red)), max(0, min(255, $green)), max(0, min(255, $blue)));
        if ($color === false) {
            throw new RuntimeException('Warna flyer belum dapat dibuat.');
        }

        return $color;
    }

    private function roundRect(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);
        foreach ([$x + $radius, $x + $width - $radius] as $centerX) {
            foreach ([$y + $radius, $y + $height - $radius] as $centerY) {
                imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $color);
            }
        }
    }

    private function text(GdImage $image, string $text, int $x, int $y, int $size, int $color, bool $bold = false): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $this->font($bold), $text);
    }

    private function center(GdImage $image, string $text, int $y, int $size, int $color, bool $bold = false, int $centerX = 540): void
    {
        $this->text($image, $text, $centerX - (int) round($this->width($text, $size, $bold) / 2), $y, $size, $color, $bold);
    }

    private function width(string $text, int $size, bool $bold = false): int
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);
        if ($box === false) {
            throw new RuntimeException('Tulisan flyer belum dapat dibuat.');
        }

        return $box[2] - $box[0];
    }

    /** @return list<string> */
    private function wrap(string $text, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($this->width($candidate, $size) <= $width) {
                $line = $candidate;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            foreach (mb_str_split($word) as $character) {
                if ($line !== '' && $this->width($line.$character, $size) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $character;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function fit(string $text, int $size, int $width): string
    {
        if ($this->width($text, $size) <= $width) {
            return $text;
        }
        while ($text !== '' && $this->width($text.'...', $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return $text.'...';
    }
}
