<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Letter;
use App\Models\LetterAssignee;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use App\Models\LetterTemplate;
use App\Models\PmbSignatory;
use App\Models\StaffMember;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LetterBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $year = now()->year;
        $filters = $this->archiveFilters($request);
        $search = trim($filters['q'] ?? '');

        $batches = $this->archiveQuery($filters)
            ->withCount([
                'letters',
                'letters as draft_school_count' => fn (Builder $query) => $query->where('institution_type', 'school'),
                'letters as draft_pesantren_count' => fn (Builder $query) => $query->where('institution_type', 'pesantren'),
            ])
            ->latest('letter_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (LetterBatch $batch): LetterBatch {
                $route = $this->sourceFormRoute($batch);
                $batch->setAttribute('source_form_url', $route === null ? null : route($route, ['from_batch' => $batch->id]));
                $batch->setAttribute('can_delete', Gate::allows('delete', $batch));

                return $batch;
            });

        return Inertia::render('letters/Index', [
            'batches' => $batches,
            'filters' => [
                'q' => $search,
                'type' => $filters['type'] ?? '',
                'destination' => $filters['destination'] ?? '',
            ],
            'stats' => [
                'lettersThisYear' => Letter::query()
                    ->whereHas('batch', fn ($query) => $query->whereYear('letter_date', $year))
                    ->count(),
                'batchesThisYear' => LetterBatch::query()
                    ->whereYear('letter_date', $year)
                    ->count(),
            ],
        ]);
    }

    public function choose(Request $request): Response|RedirectResponse
    {
        $prefill = $request->validate([
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'from_batch' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($prefill['from_batch'])) {
            $batch = LetterBatch::query()->withCount([
                'letters',
                'letters as draft_school_count' => fn (Builder $query) => $query->where('institution_type', 'school'),
                'letters as draft_pesantren_count' => fn (Builder $query) => $query->where('institution_type', 'pesantren'),
            ])->findOrFail($prefill['from_batch']);
            $route = $this->sourceFormRoute($batch);
            if ($route === null) {
                throw ValidationException::withMessages([
                    'from_batch' => in_array($batch->type, ['permission', 'assignment'], true)
                        ? 'Surat sumber harus memiliki satu jenis tujuan yang sama: sekolah atau pondok pesantren. Batch dengan tujuan campuran, kosong, atau tidak valid tidak dapat disalin.'
                        : 'Jenis surat sumber tidak didukung. Pilih jenis surat baru terlebih dahulu.',
                ]);
            }

            return to_route($route, ['from_batch' => $batch->id]);
        }

        if (isset($prefill['institution_id']) || filled($prefill['institution_name'] ?? null) || filled($prefill['district'] ?? null) || filled($prefill['address'] ?? null)) {
            $institution = isset($prefill['institution_id']) ? Institution::query()->find($prefill['institution_id']) : null;
            $destination = $institution?->institution_type ?? 'school';

            return to_route('letters.permission.'.$destination, $prefill);
        }

        return Inertia::render('letters/Start');
    }

    public function create(Request $request): Response
    {
        $prefill = $request->validate([
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'from_batch' => ['nullable', 'integer', 'min:1'],
        ]);
        $institution = isset($prefill['institution_id']) ? Institution::query()->find($prefill['institution_id']) : null;
        $routeInstitutionType = $request->route('institutionType');
        $sourceDraft = null;

        if (isset($prefill['from_batch'])) {
            $source = LetterBatch::query()->with([
                'letters' => fn (HasMany $query) => $query->orderBy('sequence_number')->orderBy('id'),
                'letters.assignees',
            ])->findOrFail($prefill['from_batch']);
            if ($source->type !== ($request->route('letterType') ?? 'permission')) {
                throw ValidationException::withMessages(['from_batch' => 'Jenis surat sumber tidak sesuai dengan formulir ini. Buka Buat surat serupa dari arsip surat.']);
            }
            if ($source->letters->isEmpty() || $source->letters->count() > 1000) {
                throw ValidationException::withMessages(['from_batch' => 'Surat sumber harus berisi 1 sampai 1.000 surat.']);
            }
            if ($source->type !== 'pkkmb' && $source->letters->contains(fn (Letter $letter): bool => $letter->institution_type !== $routeInstitutionType)) {
                throw ValidationException::withMessages(['from_batch' => 'Tujuan surat sumber tidak sesuai dengan sekolah atau pondok pada formulir ini.']);
            }
            $sourceDraft = $this->standardSourceDraft($source, $routeInstitutionType ?? 'school');
        }

        if ($institution !== null && $routeInstitutionType !== null && $institution->institution_type !== $routeInstitutionType) {
            throw ValidationException::withMessages([
                'institution_id' => 'Tujuan yang dipilih tidak sesuai dengan jenis sekolah atau pondok pada formulir.',
            ]);
        }

        $today = Carbon::now('Asia/Jakarta');
        $nextSequencesByYear = Letter::nextSequencesByYear();
        $signatory = PmbSignatory::current();

        return Inertia::render('letters/Create', [
            'today' => $today->toDateString(),
            'nextSequence' => $nextSequencesByYear[(string) $today->year] ?? 1,
            'nextSequencesByYear' => (object) $nextSequencesByYear,
            'sourceDraft' => $sourceDraft,
            'pmbSignatory' => $signatory->presentation(),
            'initialType' => $request->route('letterType') ?? 'permission',
            'initialInstitutionType' => $routeInstitutionType ?? $institution?->institution_type ?? 'school',
            'initialInstitutionId' => $institution?->id,
            'initialInstitutionName' => trim(filled($prefill['institution_name'] ?? null) ? $prefill['institution_name'] : ($institution?->name ?? '')),
            'initialDistrict' => trim(filled($prefill['district'] ?? null) ? $prefill['district'] : ($institution?->district ?? '')),
            'initialAddress' => trim(filled($prefill['address'] ?? null) ? $prefill['address'] : ($institution?->address ?? '')),
            'institutions' => Institution::query()
                ->orderBy('institution_type')
                ->orderBy('district')
                ->orderBy('name')
                ->get(['id', 'institution_type', 'name', 'district', 'address']),
            'districtOptions' => District::query()->orderBy('name')->pluck('name')->all(),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'faculties' => Faculty::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'staffMembers' => StaffMember::query()->with('district:id,name')->orderBy('name')->orderBy('id')
                ->get(['id', 'name', 'office', 'employee_number', 'district_id'])
                ->map(fn (StaffMember $person): array => [
                    ...$person->only(['id', 'name', 'office', 'employee_number', 'district_id']),
                    'district' => $person->district?->name,
                ]),
            'defaults' => [
                'numberPrefix' => LetterBatch::DEFAULT_NUMBER_PREFIX,
                'pkkmbNumberPrefix' => 'PPMB-UNUHA/TU.1.1',
                'signatoryTitle' => $signatory->title,
                'signatoryName' => $signatory->name,
                'signatoryNumber' => $signatory->number ?? '',
            ],
        ]);
    }

    public function directory(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'destination' => ['nullable', Rule::in(['school', 'pesantren'])],
            'district' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $destination = ($filters['destination'] ?? null) ?: 'school';
        unset($filters['destination']);

        return to_route($destination === 'school' ? 'master-data.schools' : 'master-data.pesantren', $filters);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');

        $rules = [
            'type' => ['required', Rule::in(['permission', 'assignment', 'pkkmb'])],
            'letter_date' => ['required', 'date'],
            'first_sequence' => ['required', 'integer', 'min:1', 'max:99999'],
            'number_prefix' => ['required', 'string', 'max:120'],
            'signatory_title' => ['required', 'string', 'max:150'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_number' => ['nullable', 'string', 'max:100'],
            'include_signature' => ['nullable', 'boolean'],
            'include_stamp' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
        ];

        if (in_array($type, ['permission', 'assignment'], true)) {
            $rules['items.*.institution_type'] = ['required', Rule::in(['school', 'pesantren'])];
        }

        if ($type === 'permission') {
            $rules['items.*.institution_id'] = ['nullable', 'integer', 'min:1'];
            $rules['items.*.recipient_address'] = ['nullable', 'string', 'max:500'];
        }

        if (in_array($type, ['assignment', 'pkkmb'], true)) {
            $rules += [
                'event_start' => ['required', 'date'],
                'event_end' => ['required', 'date', 'after_or_equal:event_start'],
            ];
        }

        if ($type === 'assignment') {
            $districts = District::query()->orderBy('name')->pluck('name')->all();

            $rules += [
                'items.*.recipient_district' => ['required', 'string', 'max:150', Rule::in($districts)],
                'items.*.assignees' => ['required', 'array', 'min:1', 'max:30'],
                'items.*.assignees.*.name' => ['required', 'string', 'max:150'],
                'items.*.assignees.*.office' => ['required', 'string', 'max:150'],
            ];
        } elseif ($type === 'permission') {
            $rules += [
                'items.*.institution_name' => ['nullable', 'required_without:items.*.institution_id', 'string', 'max:255'],
                'items.*.recipient_position' => ['required', 'string', 'max:150'],
                'items.*.recipient_name' => ['nullable', 'string', 'max:150'],
                'items.*.recipient_district' => ['nullable', 'string', 'max:150'],
            ];
        } elseif ($type === 'pkkmb') {
            $rules += [
                'pkkmb_theme' => ['required', 'string', 'max:1000'],
                'event_location' => ['nullable', 'string', 'max:255'],
                'academic_year' => ['nullable', 'string', 'max:30'],
                'items.*.participant_name' => ['required', 'string', 'max:150'],
                'items.*.participant_nim' => ['required', 'string', 'max:50'],
                'items.*.participant_study_program' => ['bail', 'required', 'string', 'max:150', Rule::exists('study_programs', 'name')],
                'items.*.participant_faculty' => ['bail', 'required', 'string', 'max:150', Rule::exists('faculties', 'name')],
                'items.*.participant_result' => ['required', Rule::in(['passed', 'not_passed'])],
                'items.*.participation_status' => ['required', Rule::in(['attended', 'completed'])],
            ];
        }

        $data = $request->validate($rules, [
            ...($type === 'assignment' ? [
                'first_sequence.required' => 'Nomor urut awal surat tugas wajib diisi.',
                'first_sequence.integer' => 'Nomor urut awal harus berupa bilangan bulat.',
                'first_sequence.min' => 'Nomor urut awal minimal 1.',
                'first_sequence.max' => 'Nomor urut awal maksimal 99999.',
            ] : []),
            'type.in' => 'Pilih jenis surat yang tersedia.',
            'items.max' => 'Jumlah surat maksimal 1.000 dalam satu pembuatan.',
            'include_signature.boolean' => 'Pilihan tanda tangan tidak valid.',
            'include_stamp.boolean' => 'Pilihan cap PMB tidak valid.',
            'event_start.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'event_start.date' => 'Tanggal mulai kegiatan tidak valid.',
            'event_end.required' => 'Tanggal selesai kegiatan wajib diisi.',
            'event_end.date' => 'Tanggal selesai kegiatan tidak valid.',
            'event_end.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai kegiatan.',
            'pkkmb_theme.required' => 'Tema kegiatan PKKMB wajib diisi.',
            'pkkmb_theme.string' => 'Tema kegiatan PKKMB harus berupa teks.',
            'pkkmb_theme.max' => 'Tema kegiatan PKKMB maksimal 1.000 karakter.',
            'event_location.string' => 'Lokasi kegiatan PKKMB harus berupa teks.',
            'event_location.max' => 'Lokasi kegiatan PKKMB maksimal 255 karakter.',
            'academic_year.string' => 'Tahun akademik PKKMB harus berupa teks.',
            'academic_year.max' => 'Tahun akademik PKKMB maksimal 30 karakter.',
            'items.*.institution_type.required' => 'Pilih tujuan surat: sekolah atau pondok pesantren.',
            'items.*.institution_type.in' => 'Tujuan surat harus sekolah atau pondok pesantren.',
            'items.*.institution_id.integer' => 'Tujuan dari daftar sekolah atau pondok tidak valid.',
            'items.*.institution_id.min' => 'Tujuan dari daftar sekolah atau pondok tidak valid.',
            'items.*.institution_name.required_without' => 'Pilih tujuan dari daftar atau isi nama sekolah atau pondok.',
            'items.*.recipient_district.required' => 'Pilih kabupaten/kota untuk kelompok dosen ini.',
            'items.*.recipient_district.in' => 'Pilih kabupaten/kota yang tersedia pada daftar tujuan.',
            'items.*.assignees.required' => 'Tambahkan dosen pada kelompok kabupaten/kota ini.',
            'items.*.assignees.min' => 'Setiap kelompok harus memiliki minimal satu dosen.',
            'items.*.assignees.max' => 'Setiap kelompok maksimal berisi 30 dosen.',
            'items.*.assignees.*.name.required' => 'Nama dosen wajib diisi.',
            'items.*.assignees.*.office.required' => 'Dinas dosen wajib diisi.',
            'items.*.recipient_address.string' => 'Alamat tujuan harus berupa teks.',
            'items.*.recipient_address.max' => 'Alamat tujuan maksimal 500 karakter.',
            'items.*.participant_name.required' => 'Nama peserta PKKMB wajib diisi.',
            'items.*.participant_name.string' => 'Nama peserta PKKMB harus berupa teks.',
            'items.*.participant_name.max' => 'Nama peserta PKKMB maksimal 150 karakter.',
            'items.*.participant_nim.required' => 'NIM peserta PKKMB wajib diisi.',
            'items.*.participant_nim.string' => 'NIM peserta PKKMB harus berupa teks.',
            'items.*.participant_nim.max' => 'NIM peserta PKKMB maksimal 50 karakter.',
            'items.*.participant_study_program.required' => 'Program studi peserta PKKMB wajib diisi.',
            'items.*.participant_study_program.string' => 'Program studi peserta PKKMB harus berupa teks.',
            'items.*.participant_study_program.max' => 'Program studi peserta PKKMB maksimal 150 karakter.',
            'items.*.participant_study_program.exists' => 'Pilih program studi yang tersedia pada master data.',
            'items.*.participant_faculty.required' => 'Fakultas peserta PKKMB wajib diisi.',
            'items.*.participant_faculty.string' => 'Fakultas peserta PKKMB harus berupa teks.',
            'items.*.participant_faculty.max' => 'Fakultas peserta PKKMB maksimal 150 karakter.',
            'items.*.participant_faculty.exists' => 'Pilih fakultas yang tersedia pada master data.',
            'items.*.participant_result.required' => 'Pilih hasil kelulusan peserta PKKMB.',
            'items.*.participant_result.in' => 'Hasil PKKMB harus lulus atau tidak lulus.',
            'items.*.participation_status.required' => 'Pilih status keikutsertaan peserta PKKMB.',
            'items.*.participation_status.in' => 'Surat keterangan PKKMB hanya dapat dibuat untuk peserta yang telah mengikuti atau telah menyelesaikan kegiatan.',
        ]);
        if ($type === 'permission') {
            $data['items'] = $this->resolveInstitutions($data['items']);
        } elseif ($type === 'assignment') {
            $data['items'] = $this->prepareAssignmentItems($data['items']);
        }

        $letterDate = Carbon::parse($data['letter_date']);
        $signatory = PmbSignatory::current();
        $data['include_signature'] = $signatory->matchesName($data['signatory_name']) && $signatory->signature_path !== null && ($data['include_signature'] ?? true);
        $data['include_stamp'] = $signatory->matchesName($data['signatory_name']) && $signatory->stamp_path !== null && ($data['include_stamp'] ?? true);

        $batches = DB::transaction(function () use ($data, $request, $letterDate, $type, $signatory) {
            if ($type === 'pkkmb') {
                $data['items'] = $this->resolvePkkmbAcademicCategories($data['items']);
            }

            $numbering = LetterNumbering::lockForYear($letterDate->year);
            $lastSequence = $numbering->minimumNextSequence() - 1;

            if ($data['first_sequence'] <= $lastSequence) {
                throw ValidationException::withMessages([
                    'first_sequence' => 'Nomor terakhir yang telah digunakan atau dicadangkan pada tahun '.$letterDate->year.' adalah '.$lastSequence.'. Gunakan nomor urut awal minimal '.($lastSequence + 1).'.',
                ]);
            }

            $finalSequence = $data['first_sequence'] + count($data['items']) - 1;
            if ($finalSequence > 99999) {
                throw ValidationException::withMessages([
                    'first_sequence' => 'Nomor akhir tahun '.$letterDate->year.' menjadi '.$finalSequence.'. Batasnya 99999; kurangi nomor urut awal atau jumlah surat.',
                ]);
            }

            $createdBatches = [];
            $sequenceOffset = 0;
            $contentTemplates = LetterTemplate::snapshotFor($type, array_values(array_unique(array_column($data['items'], 'institution_type'))));

            foreach (array_chunk($data['items'], LetterBatch::MAX_LETTERS_PER_BATCH) as $batchItems) {
                $batchFirstSequence = $data['first_sequence'] + $sequenceOffset;
                $batch = LetterBatch::query()->create([
                    'user_id' => $request->user()->id,
                    'pmb_signatory_id' => $signatory->id,
                    'type' => $type,
                    'letter_date' => $letterDate->toDateString(),
                    'first_sequence' => $batchFirstSequence,
                    'number_prefix' => trim($data['number_prefix']),
                    'pkkmb_options' => $type === 'pkkmb' ? ['version' => 1, 'theme' => trim($data['pkkmb_theme'])] : null,
                    'content_templates' => $contentTemplates,
                    'event_start' => $data['event_start'] ?? null,
                    'event_end' => $data['event_end'] ?? null,
                    'event_location' => $type === 'pkkmb' && filled($data['event_location'] ?? null) ? trim($data['event_location']) : null,
                    'academic_year' => $type === 'pkkmb' && filled($data['academic_year'] ?? null) ? trim($data['academic_year']) : null,
                    'signatory_title' => trim($data['signatory_title']),
                    'signatory_name' => trim($data['signatory_name']),
                    'signatory_number' => isset($data['signatory_number']) ? trim($data['signatory_number']) : null,
                    'include_signature' => $data['include_signature'] ?? null,
                    'include_stamp' => $data['include_stamp'] ?? null,
                ]);

                foreach ($batchItems as $index => $item) {
                    $sequence = $batchFirstSequence + $index;
                    $letterNumber = sprintf('%03d/%s/%s/%s', $sequence, trim($data['number_prefix']), $this->romanMonth($letterDate->month), $letterDate->year);

                    $letter = $batch->letters()->create([
                        'sequence_number' => $sequence,
                        'letter_number' => $letterNumber,
                        'institution_id' => $type === 'permission' ? ($item['institution_id'] ?? null) : null,
                        'recipient_position' => $type === 'permission' ? trim($item['recipient_position']) : null,
                        'recipient_name' => $type === 'permission' && filled($item['recipient_name'] ?? null) ? trim($item['recipient_name']) : null,
                        'institution_type' => $type !== 'pkkmb' ? $item['institution_type'] : null,
                        'institution_name' => $type === 'permission' && filled($item['institution_name'] ?? null) ? trim($item['institution_name']) : null,
                        'recipient_district' => $type !== 'pkkmb' && filled($item['recipient_district'] ?? null) ? trim($item['recipient_district']) : null,
                        'recipient_address' => $type === 'permission' && filled($item['recipient_address'] ?? null) ? trim($item['recipient_address']) : null,
                        'activity_destination' => $type === 'assignment' ? trim($item['activity_destination']) : null,
                        'participant_name' => $type === 'pkkmb' ? trim($item['participant_name']) : null,
                        'participant_nim' => $type === 'pkkmb' ? trim($item['participant_nim']) : null,
                        'participant_study_program' => $type === 'pkkmb' ? trim($item['participant_study_program']) : null,
                        'participant_faculty' => $type === 'pkkmb' ? trim($item['participant_faculty']) : null,
                        'participant_result' => $type === 'pkkmb' ? $item['participant_result'] : null,
                        'participation_status' => $type === 'pkkmb' ? $item['participation_status'] : null,
                    ]);

                    if ($type === 'assignment') {
                        $letter->assignees()->createMany(array_map(
                            fn (array $assignee, int $sortOrder) => [
                                'name' => trim($assignee['name']),
                                'office' => trim($assignee['office']),
                                'district' => trim($assignee['district']),
                                'sort_order' => $sortOrder,
                            ],
                            $item['assignees'],
                            array_keys($item['assignees']),
                        ));
                    }
                }

                $createdBatches[] = $batch;
                $sequenceOffset += count($batchItems);
            }

            $numbering->update(['next_sequence' => $finalSequence + 1]);

            return $createdBatches;
        }, 5);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($data['items']).' surat berhasil dibuat dalam '.count($batches).' batch.',
        ]);

        return count($batches) === 1
            ? to_route('letters.batch', ['batch' => $batches[0]->id])
            : to_route('letters.index');
    }

    public function show(LetterBatch $batch): Response
    {
        $batch->load(['letters.assignees']);
        $batch->setAttribute('can_delete', Gate::allows('delete', $batch));
        $batch->setAttribute('can_edit', Gate::allows('update', $batch));

        $signatory = $batch->pmbSignatory ?? PmbSignatory::legacy();

        return Inertia::render('letters/Batch', [
            'batch' => $batch,
            'letterheadUrl' => '/images/universitas-nurul-huda-letterhead.png?v='.hash_file('sha256', public_path('images/universitas-nurul-huda-letterhead.png')),
            'signatureOwnerName' => $signatory->name,
            'pmbSignatory' => $signatory->presentation(),
        ]);
    }

    public function destroy(Request $request, LetterBatch $batch): RedirectResponse
    {
        Gate::authorize('delete', $batch);
        $filters = $this->archiveFilters($request);
        DB::transaction(function () use ($batch): void {
            $batch = LetterBatch::query()->lockForUpdate()->findOrFail($batch->id);
            Gate::authorize('delete', $batch);
            $batch->letters()->delete();
            $batch->delete();
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch beserta suratnya dihapus dari arsip. Nomor surat tetap dicadangkan.']);

        return $this->archiveRedirect($filters);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch_ids' => ['required', 'array', 'min:1', 'max:100'],
            'batch_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'batch_ids.required' => 'Pilih setidaknya satu batch untuk dihapus.',
            'batch_ids.array' => 'Pilihan batch tidak valid.',
            'batch_ids.min' => 'Pilih setidaknya satu batch untuk dihapus.',
            'batch_ids.max' => 'Pilih maksimal 100 batch dalam satu penghapusan.',
            'batch_ids.*.required' => 'Pilihan batch tidak valid.',
            'batch_ids.*.integer' => 'Pilihan batch tidak valid.',
            'batch_ids.*.min' => 'Pilihan batch tidak valid.',
            'batch_ids.*.distinct' => 'Batch yang sama tidak boleh dipilih lebih dari sekali.',
        ]);
        $filters = $this->archiveFilters($request);

        $deletedCount = DB::transaction(function () use ($data): int {
            $batches = LetterBatch::query()->whereKey($data['batch_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($batches->count() !== count($data['batch_ids'])) {
                throw ValidationException::withMessages([
                    'batch_ids' => 'Sebagian batch sudah tidak tersedia. Muat ulang arsip dan pilih kembali.',
                ]);
            }
            foreach ($batches as $batch) {
                Gate::authorize('delete', $batch);
            }
            foreach ($batches as $batch) {
                $batch->letters()->delete();
                $batch->delete();
            }

            return $batches->count();
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => $deletedCount.' batch beserta suratnya dihapus dari arsip. Nomor surat tetap dicadangkan.']);

        return $this->archiveRedirect($filters);
    }

    public function destroyLetter(Request $request, LetterBatch $batch, Letter $letter): RedirectResponse
    {
        Gate::authorize('delete', $batch);
        abort_unless((int) $letter->letter_batch_id === (int) $batch->id, 404);
        $filters = $this->archiveFilters($request);
        $isEmpty = DB::transaction(function () use ($batch, $letter): bool {
            $batch = LetterBatch::query()->lockForUpdate()->findOrFail($batch->id);
            Gate::authorize('delete', $batch);
            $batch->letters()->whereKey($letter->id)->lockForUpdate()->firstOrFail()->delete();
            if ($batch->letters()->exists()) {
                return false;
            }
            $batch->delete();

            return true;
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Surat dihapus dari arsip. Nomor surat tetap dicadangkan.']);

        return $isEmpty ? $this->archiveRedirect($filters) : to_route('letters.batch', ['batch' => $batch->id]);
    }

    public function updateLetter(Request $request, LetterBatch $batch, Letter $letter): RedirectResponse
    {
        Gate::authorize('update', $batch);
        abort_unless((int) $letter->letter_batch_id === (int) $batch->id, 404);

        $rules = match ($batch->type) {
            'permission' => [
                'recipient_salutation' => ['nullable', 'string', 'max:150'],
                'recipient_position' => ['required', 'string', 'max:150'],
                'recipient_name' => ['nullable', 'string', 'max:150'],
                'institution_name' => ['required', 'string', 'max:255'],
                'recipient_district' => ['nullable', 'string', 'max:150'],
                'recipient_address' => ['nullable', 'string', 'max:500'],
            ],
            'assignment' => [
                'recipient_district' => ['required', 'string', 'max:150'],
                'activity_destination' => ['required', 'string', 'max:255'],
                'assignees' => ['required', 'array', 'min:1', 'max:30'],
                'assignees.*.name' => ['required', 'string', 'max:150'],
                'assignees.*.office' => ['required', 'string', 'max:150'],
            ],
            'pkkmb' => [
                'participant_name' => ['required', 'string', 'max:150'],
                'participant_nim' => ['required', 'string', 'max:50'],
                'participant_study_program' => ['required', 'string', 'max:150'],
                'participant_faculty' => ['required', 'string', 'max:150'],
                'participant_result' => ['required', Rule::in(['passed', 'not_passed'])],
                'participation_status' => ['required', Rule::in(['attended', 'completed'])],
            ],
            'general' => [
                'recipient_salutation' => ['nullable', 'string', 'max:150'],
                'recipient_position' => ['nullable', 'string', 'max:150'],
                'recipient_name' => ['nullable', 'required_without:institution_name', 'string', 'max:150'],
                'institution_name' => ['nullable', 'required_without:recipient_name', 'string', 'max:255'],
                'recipient_address' => ['nullable', 'string', 'max:500'],
            ],
            default => abort(404),
        };

        $rules += [
            'letter_date' => ['required', 'date'],
            'signatory_title' => ['required', 'string', 'max:150'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_number' => ['nullable', 'string', 'max:100'],
            'include_signature' => ['required', 'boolean'],
            'include_stamp' => ['required', 'boolean'],
            'event_start' => [$batch->type === 'assignment' || $batch->type === 'pkkmb' ? 'required' : 'nullable', 'date'],
            'event_end' => [$batch->type === 'assignment' || $batch->type === 'pkkmb' ? 'required' : 'nullable', 'date', 'after_or_equal:event_start'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['nullable', 'string', 'max:30'],
            'pkkmb_theme' => ['nullable', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:20000', 'not_regex:/<[^>]*>/'],
            'template_fields' => ['required', 'array:title,subject,attachment,basis,opening_greeting,introduction,description,closing,closing_greeting'],
        ];

        foreach (['title', 'subject', 'attachment', 'basis', 'opening_greeting', 'introduction', 'description', 'closing', 'closing_greeting'] as $field) {
            $rules['template_fields.'.$field] = ['nullable', 'string', 'max:20000'];
        }

        if ($batch->type === 'general') {
            $rules += [
                'general_options' => ['required', 'array:category,letter_title,opening_greeting,closing_greeting,closing_body,event_date,event_time,event_location,event_agenda'],
                'general_options.category' => ['nullable', 'string', 'max:50'],
                'general_options.letter_title' => ['nullable', 'string', 'max:255'],
                'general_options.opening_greeting' => ['nullable', 'string', 'max:255'],
                'general_options.closing_greeting' => ['nullable', 'string', 'max:255'],
                'general_options.closing_body' => ['nullable', 'string', 'max:20000', 'not_regex:/<[^>]*>/'],
                'general_options.event_date' => ['nullable', 'date'],
                'general_options.event_time' => ['nullable', 'string', 'max:120'],
                'general_options.event_location' => ['nullable', 'string', 'max:500'],
                'general_options.event_agenda' => ['nullable', 'string', 'max:1000'],
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($batch, $letter, $data): void {
            $lockedBatch = LetterBatch::query()->lockForUpdate()->findOrFail($batch->id);
            Gate::authorize('update', $lockedBatch);
            $lockedLetter = $lockedBatch->letters()->whereKey($letter->id)->lockForUpdate()->firstOrFail();

            $attributes = array_diff_key($data, [
                'assignees' => true,
                'letter_date' => true,
                'signatory_title' => true,
                'signatory_name' => true,
                'signatory_number' => true,
                'include_signature' => true,
                'include_stamp' => true,
                'event_start' => true,
                'event_end' => true,
                'event_location' => true,
                'academic_year' => true,
                'pkkmb_theme' => true,
                'subject' => true,
                'attachment' => true,
                'body' => true,
                'general_options' => true,
                'template_fields' => true,
            ]);
            foreach ($attributes as $key => $value) {
                $attributes[$key] = is_string($value) ? trim($value) : $value;
            }
            $lockedLetter->fill($attributes)->save();

            $lockedLetter->content_overrides = [
                'letter_date' => $data['letter_date'],
                'signatory_title' => trim($data['signatory_title']),
                'signatory_name' => trim($data['signatory_name']),
                'signatory_number' => filled($data['signatory_number'] ?? null) ? trim($data['signatory_number']) : null,
                'include_signature' => $data['include_signature'],
                'include_stamp' => $data['include_stamp'],
                'event_start' => $data['event_start'] ?? null,
                'event_end' => $data['event_end'] ?? null,
                'event_location' => filled($data['event_location'] ?? null) ? trim($data['event_location']) : null,
                'academic_year' => filled($data['academic_year'] ?? null) ? trim($data['academic_year']) : null,
                'pkkmb_theme' => filled($data['pkkmb_theme'] ?? null) ? trim($data['pkkmb_theme']) : null,
                'subject' => filled($data['subject'] ?? null) ? trim($data['subject']) : null,
                'attachment' => filled($data['attachment'] ?? null) ? trim($data['attachment']) : null,
                'body' => str_replace(["\r\n", "\r"], "\n", trim($data['body'] ?? '')),
                'general_options' => $lockedBatch->type === 'general' ? $data['general_options'] : null,
                'template_fields' => collect($data['template_fields'])
                    ->map(fn (?string $value): string => trim($value ?? ''))
                    ->all(),
            ];
            $lockedLetter->save();

            if ($lockedBatch->type === 'assignment') {
                $lockedLetter->assignees()->delete();
                foreach (array_values($data['assignees']) as $index => $assignee) {
                    $lockedLetter->assignees()->create([
                        'name' => trim($assignee['name']),
                        'office' => trim($assignee['office']),
                        'district' => trim($data['recipient_district']),
                        'sort_order' => $index,
                    ]);
                }
            }
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan surat berhasil disimpan. Nomor surat tetap.']);

        return to_route('letters.batch', ['batch' => $batch->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function archiveFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(['permission', 'assignment', 'pkkmb', 'general'])],
            'destination' => ['nullable', Rule::in(['school', 'pesantren'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function archiveQuery(array $filters): Builder
    {
        $search = trim($filters['q'] ?? '');

        return LetterBatch::query()
            ->when(filled($filters['type'] ?? null), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(filled($filters['destination'] ?? null), fn (Builder $query) => $query->whereHas('letters', fn (Builder $letters) => $letters->where('institution_type', $filters['destination'])))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $batchQuery) use ($search): void {
                    $batchQuery->where('number_prefix', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%')
                        ->orWhere('letter_date', 'like', '%'.$search.'%')
                        ->orWhereHas('letters', function (Builder $letters) use ($search): void {
                            $letters->where('letter_number', 'like', '%'.$search.'%')
                                ->orWhere('institution_name', 'like', '%'.$search.'%')
                                ->orWhere('recipient_name', 'like', '%'.$search.'%')
                                ->orWhere('recipient_position', 'like', '%'.$search.'%')
                                ->orWhere('activity_destination', 'like', '%'.$search.'%')
                                ->orWhere('recipient_district', 'like', '%'.$search.'%')
                                ->orWhere('recipient_address', 'like', '%'.$search.'%')
                                ->orWhere('participant_name', 'like', '%'.$search.'%')
                                ->orWhere('participant_nim', 'like', '%'.$search.'%');
                        });
                });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function archiveRedirect(array $filters): RedirectResponse
    {
        $lastPage = max(1, (int) ceil($this->archiveQuery($filters)->count() / 20));
        $filters['page'] = min((int) ($filters['page'] ?? 1), $lastPage);

        return to_route('letters.index', $filters);
    }

    private function sourceFormRoute(LetterBatch $batch): ?string
    {
        if (in_array($batch->type, ['general', 'pkkmb'], true)) {
            return 'letters.'.$batch->type;
        }

        $letterCount = (int) $batch->letters_count;
        if (! in_array($batch->type, ['permission', 'assignment'], true) || $letterCount < 1) {
            return null;
        }
        if ((int) $batch->draft_school_count === $letterCount) {
            return 'letters.'.$batch->type.'.school';
        }
        if ((int) $batch->draft_pesantren_count === $letterCount) {
            return 'letters.'.$batch->type.'.pesantren';
        }

        return null;
    }

    /**
     * @return array{id: int, label: string, data: array<string, mixed>}
     */
    private function standardSourceDraft(LetterBatch $source, string $destination): array
    {
        $signatory = PmbSignatory::current();
        $useCurrent = ($source->pmbSignatory ?? PmbSignatory::legacy())->matchesName($source->signatory_name);
        $isPmbSigner = $useCurrent || $signatory->matchesName($source->signatory_name);

        return [
            'id' => $source->id,
            'label' => ($source->letters->first()?->letter_number ?? 'Batch '.$source->id).' · '.$source->letters->count().' surat',
            'data' => [
                'type' => $source->type,
                'destination' => $destination,
                'number_prefix' => $source->number_prefix,
                'signatory_title' => $useCurrent ? $signatory->title : $source->signatory_title,
                'signatory_name' => $useCurrent ? $signatory->name : $source->signatory_name,
                'signatory_number' => ($useCurrent ? $signatory->number : $source->signatory_number) ?? '',
                'include_signature' => ($source->include_signature ?? $isPmbSigner) && $isPmbSigner && $signatory->signature_path !== null,
                'include_stamp' => ($source->include_stamp ?? $isPmbSigner) && $isPmbSigner && $signatory->stamp_path !== null,
                'event_start' => $source->event_start?->toDateString() ?? '',
                'event_end' => $source->event_end?->toDateString() ?? '',
                'event_location' => $source->event_location ?? '',
                'academic_year' => $source->academic_year ?? '',
                'pkkmb_theme' => ($source->pkkmb_options['version'] ?? null) === 1 ? ($source->pkkmb_options['theme'] ?? '') : '',
                'items' => $source->letters->map(fn (Letter $letter): array => [
                    'institution_id' => null,
                    'institution_type' => $letter->institution_type ?? $destination,
                    'institution_name' => $letter->institution_name ?? '',
                    'recipient_position' => $letter->recipient_position ?? '',
                    'recipient_name' => $letter->recipient_name ?? '',
                    'recipient_district' => $letter->recipient_district ?? '',
                    'recipient_address' => $letter->recipient_address ?? '',
                    'activity_destination' => $letter->activity_destination ?? '',
                    'participant_name' => $letter->participant_name ?? '',
                    'participant_nim' => $letter->participant_nim ?? '',
                    'participant_study_program' => $letter->participant_study_program ?? '',
                    'participant_faculty' => $letter->participant_faculty ?? '',
                    'participant_result' => $letter->participant_result ?? '',
                    'participation_status' => $letter->participation_status ?? '',
                    'assignees' => $letter->assignees->map(fn (LetterAssignee $person): array => [
                        'name' => $person->name,
                        'office' => $person->office,
                        'district' => $person->district,
                    ])->all(),
                ])->all(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolvePkkmbAcademicCategories(array $items): array
    {
        $studyProgramKeys = collect($items)->pluck('participant_study_program')
            ->map(fn (string $name): string => StudyProgram::identityKey($name))->unique()->all();
        $facultyKeys = collect($items)->pluck('participant_faculty')
            ->map(fn (string $name): string => Faculty::identityKey($name))->unique()->all();
        $studyPrograms = StudyProgram::query()->whereIn('identity_key', $studyProgramKeys)
            ->orderBy('id')->lockForUpdate()->get(['id', 'name', 'identity_key'])->keyBy('identity_key');
        $faculties = Faculty::query()->whereIn('identity_key', $facultyKeys)
            ->orderBy('id')->lockForUpdate()->get(['id', 'name', 'identity_key'])->keyBy('identity_key');

        foreach ($items as $index => $item) {
            $studyProgram = $studyPrograms->get(StudyProgram::identityKey($item['participant_study_program']));
            $faculty = $faculties->get(Faculty::identityKey($item['participant_faculty']));

            if ($studyProgram === null) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.participant_study_program' => 'Program studi yang dipilih telah diubah atau dihapus. Pilih kembali program studi dari master data.',
                ]);
            }

            if ($faculty === null) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.participant_faculty' => 'Fakultas yang dipilih telah diubah atau dihapus. Pilih kembali fakultas dari master data.',
                ]);
            }

            $items[$index]['participant_study_program'] = $studyProgram->name;
            $items[$index]['participant_faculty'] = $faculty->name;
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveInstitutions(array $items): array
    {
        $institutionIds = collect($items)->pluck('institution_id')->filter()->unique();
        $institutions = Institution::query()->whereKey($institutionIds)->get()->keyBy('id');

        foreach ($items as $index => $item) {
            if (! isset($item['institution_id'])) {
                continue;
            }

            $institution = $institutions->get($item['institution_id']);

            if ($institution === null) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.institution_id' => 'Tujuan sekolah atau pondok yang dipilih tidak tersedia.',
                ]);
            }

            if ($institution->institution_type !== $item['institution_type']) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.institution_id' => 'Tujuan yang dipilih tidak sesuai dengan jenis sekolah atau pondok pada surat.',
                ]);
            }

            $item['institution_name'] = filled($item['institution_name'] ?? null) ? trim($item['institution_name']) : $institution->name;
            $item['recipient_district'] = filled($item['recipient_district'] ?? null) ? trim($item['recipient_district']) : $institution->district;
            $item['recipient_address'] = filled($item['recipient_address'] ?? null) ? trim($item['recipient_address']) : $institution->address;

            $items[$index] = $item;
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareAssignmentItems(array $items): array
    {
        $groups = [];

        foreach ($items as $index => $item) {
            $district = $item['recipient_district'];
            $groupKey = $item['institution_type'].'|'.$district;

            if (isset($groups[$groupKey])) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.recipient_district' => 'Kabupaten/kota ini sudah memiliki kelompok untuk jenis tujuan yang sama. Gabungkan dosen ke kelompok tersebut.',
                ]);
            }

            $groups[$groupKey] = true;
            $items[$index] = [
                'institution_type' => $item['institution_type'],
                'recipient_district' => $district,
                'activity_destination' => $item['institution_type'] === 'pesantren' ? 'Pondok Pesantren' : 'sekolah-sekolah tingkat SMA, SMK, dan MA',
                'assignees' => array_map(
                    fn (array $person) => [
                        'name' => $person['name'],
                        'office' => $person['office'],
                        'district' => $district,
                    ],
                    $item['assignees'],
                ),
            ];
        }

        return $items;
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
