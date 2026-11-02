<?php

namespace App\Http\Controllers;

use App\Models\PmbSignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class PmbSignatoryController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        return Inertia::render('master-data/PmbSignatory', ['signatory' => PmbSignatory::current()->presentation()]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:150'], 'number' => ['nullable', 'string', 'max:100'],
            'signature' => ['nullable', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(5000)->maxHeight(5000))],
            'stamp' => ['nullable', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(5000)->maxHeight(5000))],
            'remove_signature' => ['nullable', 'boolean'], 'remove_stamp' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Isi nama kepala/ketua pusat PMB.', 'title.required' => 'Isi jabatan penandatangan PMB.',
            'signature.image' => 'Unggah gambar tanda tangan yang valid.', 'stamp.image' => 'Unggah gambar cap yang valid.',
            'signature.mimes' => 'Gunakan gambar PNG, JPG, atau WebP.', 'stamp.mimes' => 'Gunakan gambar PNG, JPG, atau WebP.',
            'signature.max' => 'Ukuran tanda tangan maksimal 2 MB.', 'stamp.max' => 'Ukuran cap maksimal 2 MB.',
        ]);
        $newPaths = [];
        try {
            DB::transaction(function () use ($request, $data, &$newPaths): void {
                PmbSignatory::query()->oldest('id')->lockForUpdate()->firstOrFail();
                $current = PmbSignatory::current();
                if ((int) $data['revision'] !== $current->id) {
                    throw ValidationException::withMessages(['revision' => 'Data penandatangan sudah berubah. Muat ulang halaman sebelum menyimpan.']);
                }
                $paths = ['signature_path' => $current->matchesName($data['name']) ? $current->signature_path : null, 'stamp_path' => $current->stamp_path];
                foreach (['signature', 'stamp'] as $kind) {
                    if (($data['remove_'.$kind] ?? false) && $request->hasFile($kind)) {
                        throw ValidationException::withMessages([$kind => 'Pilih unggah gambar baru atau hapus gambar lama.']);
                    }
                    if ($data['remove_'.$kind] ?? false) {
                        $paths[$kind.'_path'] = null;
                    }
                    if ($request->hasFile($kind)) {
                        $path = $request->file($kind)->store('pmb-signatories', 'local');
                        if ($path === false) {
                            throw new \RuntimeException('Gambar belum dapat disimpan.');
                        }
                        $newPaths[] = $path;
                        $paths[$kind.'_path'] = $path;
                    }
                }
                PmbSignatory::query()->create([
                    'name' => trim($data['name']), 'title' => trim($data['title']), 'number' => filled($data['number'] ?? null) ? trim($data['number']) : null,
                    ...$paths, 'updated_by' => $request->user()->id,
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data penandatangan PMB diperbarui untuk surat dan daftar hadir baru.']);

        return to_route('master-data.pmb-signatory.edit');
    }

    public function media(PmbSignatory $signatory, string $kind): HttpResponse
    {
        abort_unless(in_array($kind, ['signature', 'stamp'], true), 404);
        $path = $signatory->getAttribute($kind.'_path');
        abort_unless(is_string($path) && $path !== '', 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=31536000, immutable'];
        if (in_array($path, ['images/pmb-signature.png', 'images/pmb-stamp.png'], true)) {
            return response()->file(public_path($path), ['Content-Type' => 'image/png', ...$headers]);
        }
        abort_unless(str_starts_with($path, 'pmb-signatories/') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, $headers);
    }
}
