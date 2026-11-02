<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class GuideBookController extends Controller
{
    private const FILE_PATH = 'guides/Panduan-SISURAT-Tutorial-Bernomor.pdf';

    private const FILE_NAME = 'Panduan-SISURAT-Tutorial-Bernomor.pdf';

    public function show(): BinaryFileResponse
    {
        return response()->file($this->filePath(), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, self::FILE_NAME);
    }

    public function download(): BinaryFileResponse
    {
        return response()->download($this->filePath(), self::FILE_NAME, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filePath(): string
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists(self::FILE_PATH), 404, 'Buku panduan belum tersedia.');

        return $disk->path(self::FILE_PATH);
    }
}
