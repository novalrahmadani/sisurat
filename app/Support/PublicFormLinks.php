<?php

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

class PublicFormLinks
{
    /** @param array<string, int|string> $parameters */
    public function url(string $name, array $parameters = []): string
    {
        $base = config('internship.public_url');
        if (is_string($base) && filter_var($base, FILTER_VALIDATE_URL) !== false && parse_url($base, PHP_URL_SCHEME) === 'https') {
            return rtrim($base, '/').route($name, $parameters, false);
        }

        return route($name, $parameters);
    }

    /** @param array<string, int|string> $parameters */
    public function qrCode(string $name, array $parameters, string $filename, bool $download): Response
    {
        $image = $this->qrImage($name, $parameters);

        return response($image, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, int|string> $parameters */
    public function qrImage(string $name, array $parameters = []): string
    {
        return (new Writer(new GDLibRenderer(768, 4)))->writeString($this->url($name, $parameters));
    }
}
