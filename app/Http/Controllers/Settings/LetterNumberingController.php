<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LetterNumberingController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($data['year'] ?? now()->year);
        $numbering = LetterNumbering::query()->where('year', $year)->first();
        $lastSequence = Letter::lastOfficialSequenceForYear($year);

        return Inertia::render('settings/LetterNumbering', [
            'year' => $year,
            'nextSequence' => max($lastSequence + 1, (int) ($numbering->next_sequence ?? 1)),
            'configured' => $numbering?->configured_at !== null,
            'lastOfficialSequence' => $lastSequence,
            'trialBatchCount' => LetterBatch::withTrashed()->whereYear('letter_date', $year)->where('is_trial', true)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'next_sequence' => ['required', 'integer', 'between:1,99999'],
            'expected_next_sequence' => ['required', 'integer', 'between:1,100000'],
        ], [
            'next_sequence.required' => 'Isi nomor surat berikutnya setelah nomor terakhir pada buku agenda.',
            'next_sequence.integer' => 'Nomor surat harus berupa bilangan bulat.',
            'next_sequence.between' => 'Nomor surat harus antara 1 dan 99999.',
        ]);
        DB::transaction(function () use ($request, $data): void {
            $numbering = LetterNumbering::lockForYear((int) $data['year']);
            if ($numbering->minimumNextSequence() !== (int) $data['expected_next_sequence']) {
                throw ValidationException::withMessages(['next_sequence' => 'Penomoran telah berubah. Muat ulang halaman dan periksa nomor terbaru.']);
            }
            $minimum = Letter::lastOfficialSequenceForYear((int) $data['year']) + 1;
            if ((int) $data['next_sequence'] < $minimum) {
                throw ValidationException::withMessages(['next_sequence' => 'Nomor surat resmi tahun '.$data['year'].' sudah digunakan atau dicadangkan sampai '.($minimum - 1).'. Gunakan minimal '.$minimum.'.']);
            }
            $numbering->update([
                'next_sequence' => $data['next_sequence'],
                'updated_by' => $request->user()->id,
                'configured_at' => now(),
            ]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nomor surat berikutnya untuk tahun '.$data['year'].' telah disimpan.']);

        return to_route('letter-numbering.edit', ['year' => $data['year']]);
    }
}
