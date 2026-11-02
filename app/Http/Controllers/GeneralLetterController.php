<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use App\Models\PmbSignatory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLetterController extends Controller
{
    public function create(Request $request): Response
    {
        $prefill = $request->validate(['from_batch' => ['nullable', 'integer', 'min:1']]);
        $sourceDraft = null;
        if (isset($prefill['from_batch'])) {
            $source = LetterBatch::query()->with([
                'letters' => fn (HasMany $query) => $query->orderBy('sequence_number')->orderBy('id'),
                'letters.assignees',
            ])->findOrFail($prefill['from_batch']);
            if ($source->type !== 'general') {
                throw ValidationException::withMessages(['from_batch' => 'Surat sumber harus berupa Surat Umum. Buka Buat surat serupa dari arsip surat.']);
            }
            if ($source->letters->isEmpty() || $source->letters->count() > 1000) {
                throw ValidationException::withMessages(['from_batch' => 'Surat sumber harus berisi 1 sampai 1.000 surat.']);
            }
            $sourceDraft = $this->generalSourceDraft($source);
        }

        $today = Carbon::now('Asia/Jakarta');
        $nextSequencesByYear = Letter::nextSequencesByYear();
        $signatory = PmbSignatory::current();

        return Inertia::render('letters/GeneralCreate', [
            'today' => $today->toDateString(),
            'nextSequence' => $nextSequencesByYear[(string) $today->year] ?? 1,
            'nextSequencesByYear' => (object) $nextSequencesByYear,
            'sourceDraft' => $sourceDraft,
            'pmbSignatory' => $signatory->presentation(),
            'defaults' => [
                'numberPrefix' => 'PPMBPM-UNUHA/TU.2.1',
                'signatoryTitle' => $signatory->title,
                'signatoryName' => $signatory->name,
                'signatoryNumber' => $signatory->number ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'letter_date' => ['required', 'date'],
            'first_sequence' => ['required', 'integer', 'min:1', 'max:99999'],
            'number_prefix' => ['required', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(['general', 'invitation', 'meeting', 'other'])],
            'letter_title' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'attachment' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000', 'not_regex:/<\/?[a-z][^>]*>|<!--|<!doctype/iu'],
            'closing_body' => ['nullable', 'string', 'max:20000', 'not_regex:/<\/?[a-z][^>]*>|<!--|<!doctype/iu'],
            'opening_greeting' => ['nullable', 'string', 'max:255'],
            'closing_greeting' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'string', 'max:120'],
            'event_location' => ['nullable', 'string', 'max:500'],
            'event_agenda' => ['nullable', 'string', 'max:1000'],
            'signatory_title' => ['required', 'string', 'max:150'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_number' => ['nullable', 'string', 'max:100'],
            'include_signature' => ['nullable', 'boolean'],
            'include_stamp' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.institution_name' => ['nullable', 'required_without:items.*.recipient_name', 'string', 'max:255'],
            'items.*.recipient_position' => ['nullable', 'string', 'max:150'],
            'items.*.recipient_name' => ['nullable', 'required_without:items.*.institution_name', 'string', 'max:150'],
            'items.*.recipient_salutation' => ['nullable', 'string', 'max:150'],
            'items.*.recipient_address' => ['nullable', 'string', 'max:500'],
        ], [
            'first_sequence.required' => 'Nomor urut awal wajib diisi.',
            'first_sequence.integer' => 'Nomor urut awal harus berupa bilangan bulat.',
            'first_sequence.min' => 'Nomor urut awal minimal 1.',
            'first_sequence.max' => 'Nomor urut awal maksimal 99999.',
            'number_prefix.required' => 'Kode surat wajib diisi.',
            'number_prefix.max' => 'Kode surat maksimal 120 karakter.',
            'category.in' => 'Kategori surat tidak valid.',
            'letter_title.max' => 'Judul surat maksimal 255 karakter.',
            'subject.required' => 'Perihal surat wajib diisi.',
            'subject.max' => 'Perihal maksimal 255 karakter.',
            'attachment.max' => 'Lampiran maksimal 120 karakter.',
            'body.required' => 'Pengantar surat wajib diisi.',
            'body.max' => 'Pengantar surat maksimal 20.000 karakter.',
            'body.not_regex' => 'Gunakan teks biasa untuk pengantar surat, tanpa tag HTML.',
            'closing_body.string' => 'Isi penutup surat harus berupa teks.',
            'closing_body.max' => 'Isi penutup surat maksimal 20.000 karakter.',
            'closing_body.not_regex' => 'Gunakan teks biasa untuk isi penutup surat, tanpa tag HTML.',
            'opening_greeting.max' => 'Salam pembuka maksimal 255 karakter.',
            'closing_greeting.max' => 'Salam penutup maksimal 255 karakter.',
            'event_date.date' => 'Tanggal kegiatan tidak valid.',
            'event_time.max' => 'Waktu kegiatan maksimal 120 karakter.',
            'event_location.max' => 'Tempat kegiatan maksimal 500 karakter.',
            'event_agenda.max' => 'Agenda kegiatan maksimal 1.000 karakter.',
            'items.required' => 'Tambahkan penerima surat.',
            'items.min' => 'Tambahkan minimal satu penerima surat.',
            'items.max' => 'Jumlah surat maksimal 1.000 dalam satu pembuatan.',
            'items.*.institution_name.required_without' => 'Isi nama instansi/tujuan atau nama penerima.',
            'items.*.institution_name.max' => 'Nama instansi/tujuan maksimal 255 karakter.',
            'items.*.recipient_position.max' => 'Jabatan penerima maksimal 150 karakter.',
            'items.*.recipient_name.required_without' => 'Isi nama penerima atau nama instansi/tujuan.',
            'items.*.recipient_name.max' => 'Nama penerima maksimal 150 karakter.',
            'items.*.recipient_salutation.max' => 'Sapaan penerima maksimal 150 karakter.',
            'items.*.recipient_address.max' => 'Alamat penerima maksimal 500 karakter.',
            'include_signature.boolean' => 'Pilihan tanda tangan tidak valid.',
            'include_stamp.boolean' => 'Pilihan cap PMB tidak valid.',
        ]);
        $letterDate = Carbon::parse($data['letter_date']);
        $signatory = PmbSignatory::current();
        $isPmbSigner = $signatory->matchesName($data['signatory_name']);
        $data['include_signature'] = $isPmbSigner && $signatory->signature_path !== null && ($data['include_signature'] ?? true);
        $data['include_stamp'] = $isPmbSigner && $signatory->stamp_path !== null && ($data['include_stamp'] ?? true);

        if (! $isPmbSigner) {
            $data['include_signature'] = false;
            $data['include_stamp'] = false;
        }

        $generalOptions = [
            'version' => 1,
            'category' => $data['category'] ?? 'general',
            'letter_title' => filled($data['letter_title'] ?? null) ? trim($data['letter_title']) : null,
            'closing_body' => str_replace(["\r\n", "\r"], "\n", trim($data['closing_body'] ?? '')),
            'opening_greeting' => array_key_exists('opening_greeting', $data)
                ? trim($data['opening_greeting'] ?? '')
                : 'Assalamu’alaikum Warahmatullahi Wabarakatuh',
            'closing_greeting' => array_key_exists('closing_greeting', $data)
                ? trim($data['closing_greeting'] ?? '')
                : 'Wassalamu’alaikum Warahmatullahi Wabarakatuh',
            'event_date' => filled($data['event_date'] ?? null) ? Carbon::parse($data['event_date'])->toDateString() : null,
            'event_time' => filled($data['event_time'] ?? null) ? trim($data['event_time']) : null,
            'event_location' => filled($data['event_location'] ?? null) ? trim($data['event_location']) : null,
            'event_agenda' => filled($data['event_agenda'] ?? null) ? trim($data['event_agenda']) : null,
        ];

        $batches = DB::transaction(function () use ($data, $request, $letterDate, $generalOptions, $signatory): array {
            $items = $this->prepareRecipients($data['items']);
            $numbering = LetterNumbering::lockForYear($letterDate->year);
            $lastSequence = $numbering->minimumNextSequence() - 1;

            if ($data['first_sequence'] <= $lastSequence) {
                throw ValidationException::withMessages([
                    'first_sequence' => 'Nomor terakhir yang telah digunakan atau dicadangkan pada tahun '.$letterDate->year.' adalah '.$lastSequence.'. Gunakan nomor urut awal minimal '.($lastSequence + 1).'.',
                ]);
            }

            $finalSequence = $data['first_sequence'] + count($items) - 1;
            if ($finalSequence > 99999) {
                throw ValidationException::withMessages([
                    'first_sequence' => 'Nomor akhir tahun '.$letterDate->year.' menjadi '.$finalSequence.'. Batasnya 99999; kurangi nomor urut awal atau jumlah penerima.',
                ]);
            }

            $batches = [];
            $offset = 0;
            foreach (array_chunk($items, LetterBatch::MAX_LETTERS_PER_BATCH) as $batchItems) {
                $firstSequence = $data['first_sequence'] + $offset;
                $batch = LetterBatch::query()->create([
                    'user_id' => $request->user()->id,
                    'pmb_signatory_id' => $signatory->id,
                    'type' => 'general',
                    'letter_date' => $letterDate->toDateString(),
                    'first_sequence' => $firstSequence,
                    'number_prefix' => trim($data['number_prefix']),
                    'subject' => trim($data['subject']),
                    'attachment' => filled($data['attachment'] ?? null) ? trim($data['attachment']) : null,
                    'body' => str_replace(["\r\n", "\r"], "\n", trim($data['body'])),
                    'general_options' => $generalOptions,
                    'signatory_title' => trim($data['signatory_title']),
                    'signatory_name' => trim($data['signatory_name']),
                    'signatory_number' => filled($data['signatory_number'] ?? null) ? trim($data['signatory_number']) : null,
                    'include_signature' => $data['include_signature'] ?? null,
                    'include_stamp' => $data['include_stamp'] ?? null,
                ]);

                foreach ($batchItems as $index => $item) {
                    $sequence = $firstSequence + $index;
                    $batch->letters()->create([
                        'sequence_number' => $sequence,
                        'letter_number' => sprintf('%03d/%s/%s/%s', $sequence, trim($data['number_prefix']), $this->romanMonth($letterDate->month), $letterDate->year),
                        'institution_id' => null,
                        'institution_type' => null,
                        'institution_name' => filled($item['institution_name'] ?? null) ? trim($item['institution_name']) : null,
                        'recipient_salutation' => array_key_exists('recipient_salutation', $item) ? trim($item['recipient_salutation'] ?? '') : 'Kepada Yth.',
                        'recipient_position' => filled($item['recipient_position'] ?? null) ? trim($item['recipient_position']) : null,
                        'recipient_name' => filled($item['recipient_name'] ?? null) ? trim($item['recipient_name']) : null,
                        'recipient_district' => null,
                        'recipient_address' => filled($item['recipient_address'] ?? null) ? trim($item['recipient_address']) : null,
                    ]);
                }

                $batches[] = $batch;
                $offset += count($batchItems);
            }

            $numbering->update(['next_sequence' => $finalSequence + 1]);

            return $batches;
        }, 5);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($data['items']).' surat umum berhasil dibuat dalam '.count($batches).' batch.',
        ]);

        return count($batches) === 1
            ? to_route('letters.batch', ['batch' => $batches[0]->id])
            : to_route('letters.index');
    }

    /**
     * @return array{id: int, label: string, data: array<string, mixed>}
     */
    private function generalSourceDraft(LetterBatch $source): array
    {
        $options = $source->general_options;
        $signatory = PmbSignatory::current();
        $useCurrent = ($source->pmbSignatory ?? PmbSignatory::legacy())->matchesName($source->signatory_name);
        $isPmbSigner = $useCurrent || $signatory->matchesName($source->signatory_name);

        return [
            'id' => $source->id,
            'label' => ($source->letters->first()?->letter_number ?? 'Batch '.$source->id).' · '.$source->letters->count().' surat',
            'data' => [
                'type' => 'general',
                'category' => $options['category'] ?? 'general',
                'letter_title' => $options['letter_title'] ?? '',
                'number_prefix' => $source->number_prefix,
                'subject' => $source->subject ?? '',
                'attachment' => $source->attachment ?? '-',
                'body' => $source->body ?? '',
                'closing_body' => $options['closing_body'] ?? '',
                'opening_greeting' => $options === null ? 'Assalamu’alaikum Warahmatullahi Wabarakatuh' : ($options['opening_greeting'] ?? ''),
                'closing_greeting' => $options === null ? 'Wassalamu’alaikum Warahmatullahi Wabarakatuh' : ($options['closing_greeting'] ?? ''),
                'event_date' => $options['event_date'] ?? '',
                'event_time' => $options['event_time'] ?? '',
                'event_location' => $options['event_location'] ?? '',
                'event_agenda' => $options['event_agenda'] ?? '',
                'signatory_title' => $useCurrent ? $signatory->title : $source->signatory_title,
                'signatory_name' => $useCurrent ? $signatory->name : $source->signatory_name,
                'signatory_number' => ($useCurrent ? $signatory->number : $source->signatory_number) ?? '',
                'include_signature' => ($source->include_signature ?? $isPmbSigner) && $isPmbSigner && $signatory->signature_path !== null,
                'include_stamp' => ($source->include_stamp ?? $isPmbSigner) && $isPmbSigner && $signatory->stamp_path !== null,
                'items' => $source->letters->map(fn (Letter $letter): array => [
                    'institution_name' => $letter->institution_name ?? '',
                    'recipient_position' => $letter->recipient_position ?? '',
                    'recipient_name' => $letter->recipient_name ?? '',
                    'recipient_salutation' => $options === null ? 'Kepada Yth.' : ($letter->recipient_salutation ?? 'Kepada Yth.'),
                    'recipient_address' => $options === null ? 'Tempat' : ($letter->recipient_address ?? 'Tempat'),
                ])->all(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareRecipients(array $items): array
    {
        $seenRecipients = [];

        foreach ($items as $index => $item) {
            $recipient = hash('sha256', implode("\0", [
                $this->identityPart($item['institution_name'] ?? ''),
                $this->identityPart($item['recipient_name'] ?? ''),
                $this->identityPart($item['recipient_position'] ?? ''),
                $this->identityPart($item['recipient_address'] ?? ''),
            ]));

            if (isset($seenRecipients[$recipient])) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.recipient_name' => 'Penerima ini sudah ditambahkan. Gunakan satu surat untuk penerima, instansi, jabatan, dan alamat yang sama.',
                ]);
            }

            $seenRecipients[$recipient] = true;
        }

        return $items;
    }

    private function identityPart(string $value): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value), 'UTF-8')) ?? trim($value);
    }

    private function romanMonth(int $month): string
    {
        return [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ][$month];
    }
}
