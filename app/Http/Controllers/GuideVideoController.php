<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class GuideVideoController extends Controller
{
    private const FILE_NAME = 'Tutorial-SISURAT.mp4';

    public function show(): BinaryFileResponse
    {
        return response()->file($this->filePath(self::FILE_NAME), $this->headers('video/mp4'))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, self::FILE_NAME);
    }

    public function download(): BinaryFileResponse
    {
        return response()->download($this->filePath(self::FILE_NAME), self::FILE_NAME, $this->headers('video/mp4'));
    }

    public function poster(): BinaryFileResponse
    {
        return response()->file($this->filePath('Tutorial-SISURAT-poster.png'), $this->headers('image/png'));
    }

    /** @return array<string, string> */
    private function headers(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function filePath(string $name): string
    {
        $disk = Storage::disk('local');
        $path = 'guides/'.$name;

        abort_unless($disk->exists($path), 404, 'Video panduan belum tersedia.');

        return $disk->path($path);
    }
}
