<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File as FileRule;
use Inertia\Inertia;
use Inertia\Response;

class LetterheadController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        return Inertia::render('master-data/Letterhead', [
            'letterheadUrl' => $this->letterheadUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        $data = $request->validate([
            'letterhead' => [
                'required',
                FileRule::image()->types(['png'])->max(5120)
                    ->dimensions(Rule::dimensions()->maxWidth(5000)->maxHeight(1500)->minRatio(5)),
            ],
        ], [
            'letterhead.required' => 'Pilih gambar kop surat terlebih dahulu.',
            'letterhead.image' => 'Unggah gambar kop surat yang valid.',
            'letterhead.mimes' => 'Gunakan gambar PNG.',
            'letterhead.max' => 'Ukuran gambar maksimal 5 MB.',
            'letterhead.dimensions' => 'Unggah kop melebar dengan ukuran maksimal 5.000 × 1.500 piksel dan rasio minimal 5:1 agar pas pada kertas A4.',
        ]);

        $path = public_path('images/universitas-nurul-huda-letterhead.png');
        abort_if(File::put($path, $data['letterhead']->get()) === false, 500, 'Kop surat gagal disimpan.');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kop surat PKKMB berhasil diperbarui.']);

        return to_route('master-data.letterhead.edit');
    }

    private function letterheadUrl(): string
    {
        $path = public_path('images/universitas-nurul-huda-letterhead.png');

        $version = File::exists($path) ? (hash_file('sha256', $path) ?: '1') : '1';

        return '/images/universitas-nurul-huda-letterhead.png?v=' . $version;
    }
}

