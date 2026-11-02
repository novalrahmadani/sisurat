<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuranAssessmentRequest;
use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\StudyProgram;
use App\Models\User;
use App\Support\QuranAssessmentPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuranAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $creatorId = (int) $request->user()->id;
        $assessments = $this->assessmentsQuery($creatorId, $filters)
            ->select(['id', 'quran_exam_id', 'wave', 'period', 'academic_year', 'campus', 'name', 'registration_number', 'study_program', 'assessment_date', 'examiner_name', 'result', 'fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score'])
            ->orderByDesc('assessment_date')->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (QuranAssessment $assessment): array => $this->assessmentSummary($assessment));
        $ownedAssessments = QuranAssessment::query()->where('created_by', $creatorId);

        return $this->privatePage($request, 'quran-tests/Index', [
            'assessments' => $assessments,
            'filters' => ['q' => $filters['q'], 'result' => $filters['result'], 'exam_id' => $filters['exam_id']],
            'exams' => QuranExam::query()->where('created_by', $creatorId)->orderByDesc('assessment_date')->get(['id', 'title']),
            'stats' => [
                'total' => (clone $ownedAssessments)->count(),
                'pending' => (clone $ownedAssessments)->where('result', QuranAssessment::RESULT_PENDING)->count(),
                'completed' => (clone $ownedAssessments)->whereIn('result', QuranAssessment::MQ_RESULTS)->count(),
                'mq1' => (clone $ownedAssessments)->where('result', QuranAssessment::RESULT_MQ1)->count(),
                'mq2' => (clone $ownedAssessments)->where('result', QuranAssessment::RESULT_MQ2)->count(),
                'legacy' => (clone $ownedAssessments)->whereNotIn('result', [QuranAssessment::RESULT_PENDING, ...QuranAssessment::MQ_RESULTS])->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->privatePage($request, 'quran-tests/Form', $this->formProps($request));
    }

    public function store(QuranAssessmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assessment = DB::transaction(function () use ($request, $data): QuranAssessment {
            $creator = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($creator->is_active, 403, 'Akun ini tidak aktif.');
            $existing = QuranAssessment::query()->where('created_by', $creator->id)
                ->where('submission_token', $data['submission_token'])->first();
            if ($existing !== null) {
                return $existing;
            }

            return QuranAssessment::query()->create([
                ...Arr::only($data, ['wave', 'period', 'academic_year', 'campus']),
                ...$this->assessmentInput($data),
                'study_program' => $this->studyProgramName((int) $data['study_program_id']),
                'created_by' => $creator->id,
                'version' => (string) Str::uuid(),
                'submission_token' => $data['submission_token'],
            ]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian baca Al-Qur’an berhasil disimpan.']);

        return to_route('quran-tests.show', ['quran_test' => $assessment->id]);
    }

    public function show(Request $request, QuranAssessment $quranTest): Response
    {
        Gate::authorize('view', $quranTest);

        return $this->privatePage($request, 'quran-tests/Show', ['assessment' => $this->assessmentData($quranTest)]);
    }

    public function edit(Request $request, QuranAssessment $quranTest): Response
    {
        Gate::authorize('update', $quranTest);

        return $this->privatePage($request, 'quran-tests/Form', $this->formProps($request, $quranTest));
    }

    public function update(QuranAssessmentRequest $request, QuranAssessment $quranTest): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $quranTest): void {
            $lockedAssessment = QuranAssessment::query()->lockForUpdate()->findOrFail($quranTest->id);
            Gate::authorize('update', $lockedAssessment);
            if ($data['version'] !== $lockedAssessment->version) {
                throw ValidationException::withMessages([
                    'version' => 'Penilaian telah diubah dari halaman lain. Salin isian Anda bila diperlukan, lalu muat ulang halaman dan periksa perubahan sebelum menyimpan kembali.',
                ]);
            }
            if ($lockedAssessment->quran_exam_id !== null) {
                QuranExam::query()->lockForUpdate()->findOrFail($lockedAssessment->quran_exam_id);
                if (QuranAssessment::query()->where('quran_exam_id', $lockedAssessment->quran_exam_id)
                    ->where('registration_key', QuranAssessment::registrationKey($data['registration_number']))->whereKeyNot($lockedAssessment->id)->exists()) {
                    throw ValidationException::withMessages(['registration_number' => 'Nomor registrasi sudah digunakan peserta lain pada kegiatan tes ini.']);
                }
            }
            $lockedAssessment->update([
                ...Arr::only($data, ['wave', 'period', 'academic_year', 'campus']),
                ...$this->assessmentInput($data),
                'study_program' => (bool) $data['retain_study_program']
                    ? $lockedAssessment->study_program
                    : $this->studyProgramName((int) $data['study_program_id']),
                'version' => (string) Str::uuid(),
            ]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian baca Al-Qur’an berhasil diperbarui.']);

        return to_route('quran-tests.show', ['quran_test' => $quranTest->id]);
    }

    public function destroy(Request $request, QuranAssessment $quranTest): RedirectResponse
    {
        Gate::authorize('delete', $quranTest);
        $filters = $this->filters($request);
        DB::transaction(function () use ($quranTest): void {
            $lockedAssessment = QuranAssessment::query()->lockForUpdate()->findOrFail($quranTest->id);
            Gate::authorize('delete', $lockedAssessment);
            $lockedAssessment->delete();
        }, 5);
        $remaining = $this->assessmentsQuery((int) $request->user()->id, $filters)->count();
        $filters['page'] = min($filters['page'], max(1, (int) ceil($remaining / 20)));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian baca Al-Qur’an berhasil dihapus.']);

        return to_route('quran-tests.index', $filters);
    }

    public function print(QuranAssessment $quranTest): HttpResponse
    {
        Gate::authorize('view', $quranTest);

        return response()->view('quran-tests.assessment', ['assessment' => $quranTest])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pdf(Request $request, QuranAssessmentPdf $report): Response
    {
        $filters = $this->filters($request);
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::location(route('quran-tests.pdf', $filters));
        }
        $rows = $this->assessmentsQuery((int) $request->user()->id, $filters)->orderBy('assessment_date')->orderBy('id')->get();
        $exam = $filters['exam_id'] === null ? null : QuranExam::query()->where('created_by', $request->user()->id)->find($filters['exam_id']);
        $scope = $exam->title ?? 'Seluruh penilaian Tes Baca Al-Qur’an';

        return response($report->render($rows, $scope, $filters), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="rekap-tes-baca-alquran.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $creatorId = (int) $request->user()->id;
        $filename = 'hasil-tes-baca-alquran-'.now('Asia/Jakarta')->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($creatorId, $filters): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('File CSV belum dapat disiapkan.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['No', 'Tanggal Tes', 'Nama', 'Nomor Registrasi', 'Program Studi', 'Penguji', 'Surah/Ayat yang Dibaca', 'Fasohah', 'Tajwid', 'Makhroj', 'Kelancaran', 'Total (400)', 'Rata-rata (100)', 'Kategori MQ / Status', 'Catatan', 'Dibuat (WIB)', 'Diperbarui (WIB)', 'Gelombang', 'Periode', 'Tahun Akademik', 'Lokasi Kampus'], ',', '"', '');
            $number = 0;
            $assessments = $this->assessmentsQuery($creatorId, $filters)
                ->select(['id', 'quran_exam_id', 'wave', 'period', 'academic_year', 'campus', 'name', 'registration_number', 'study_program', 'assessment_date', 'examiner_name', 'reading_reference', 'fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score', 'result', 'notes', 'created_at', 'updated_at']);
            foreach ($assessments->lazyById(250) as $assessment) {
                $session = $assessment->testSessionDetails();
                fputcsv($output, [
                    ++$number,
                    $assessment->assessment_date->format('d-m-Y'),
                    $this->csvCell($assessment->name),
                    "'".$assessment->registration_number,
                    $this->csvCell($assessment->study_program),
                    $this->csvCell($assessment->examiner_name ?? ''),
                    $this->csvCell($assessment->reading_reference ?? ''),
                    $assessment->fasohah_score,
                    $assessment->tajwid_score,
                    $assessment->makhraj_score,
                    $assessment->fluency_score,
                    $assessment->totalScore(),
                    $assessment->averageScore(),
                    $this->csvCell(QuranAssessment::RESULT_LABELS[$assessment->result]),
                    $this->csvCell($assessment->notes ?? ''),
                    $assessment->created_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s'),
                    $assessment->updated_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s'),
                    $session['wave'] === null ? '' : 'Gelombang '.$session['wave'],
                    $this->csvCell($session['period'] ?? ''),
                    $this->csvCell($session['academic_year'] ?? ''),
                    $session['campus'] === null ? '' : 'Kampus '.$session['campus'],
                ], ',', '"', '');
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * @return array{q: string, result: string, page: int, exam_id: int|null}
     */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'result' => ['nullable', Rule::in(['all', ...QuranAssessment::RESULTS])],
            'page' => ['nullable', 'integer', 'min:1'],
            'exam_id' => ['nullable', 'integer', Rule::exists('quran_exams', 'id')->where('created_by', $request->user()->id)],
        ]);

        return [
            'q' => Str::trim($filters['q'] ?? ''),
            'result' => $filters['result'] ?? 'all',
            'page' => (int) ($filters['page'] ?? 1),
            'exam_id' => isset($filters['exam_id']) ? (int) $filters['exam_id'] : null,
        ];
    }

    /**
     * @param  array{q: string, result: string, page: int, exam_id: int|null}  $filters
     * @return Builder<QuranAssessment>
     */
    private function assessmentsQuery(int $creatorId, array $filters): Builder
    {
        return QuranAssessment::query()->with('exam')->where('created_by', $creatorId)
            ->when($filters['exam_id'] !== null, fn (Builder $query) => $query->where('quran_exam_id', $filters['exam_id']))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $search = '%'.$filters['q'].'%';
                    $query->where('name', 'like', $search)->orWhere('registration_number', 'like', $search)
                        ->orWhere('study_program', 'like', $search)->orWhere('examiner_name', 'like', $search);
                });
            })
            ->when($filters['result'] !== 'all', fn (Builder $query) => $query->where('result', $filters['result']));
    }

    /** @return array<string, mixed> */
    private function formProps(Request $request, ?QuranAssessment $assessment = null): array
    {
        return [
            'assessment' => $assessment === null ? null : $this->assessmentData($assessment),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name']),
            'today' => now('Asia/Jakarta')->format('Y-m-d'),
            'defaultExaminer' => $request->user()->name,
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentSummary(QuranAssessment $assessment): array
    {
        return [
            ...$assessment->only(['id', 'name', 'registration_number', 'study_program', 'examiner_name', 'result']),
            ...$assessment->testSessionDetails(),
            'assessment_date' => $assessment->assessment_date->format('Y-m-d'),
            'average_score' => $assessment->averageScore(),
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentData(QuranAssessment $assessment): array
    {
        return [
            ...$this->assessmentSummary($assessment),
            'quran_exam_id' => $assessment->quran_exam_id,
            ...$assessment->only(['reading_reference', 'fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score', 'notes', 'version']),
            'created_at' => $assessment->created_at->toIso8601String(),
            'updated_at' => $assessment->updated_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function assessmentInput(array $data): array
    {
        return [
            'name' => $data['name'],
            'registration_number' => $data['registration_number'],
            'registration_key' => QuranAssessment::registrationKey($data['registration_number']),
            'assessment_date' => $data['assessment_date'],
            'examiner_name' => $data['examiner_name'] ?? null,
            'reading_reference' => $data['reading_reference'] ?? null,
            'fasohah_score' => $data['fasohah_score'] ?? null,
            'makhraj_score' => $data['makhraj_score'] ?? null,
            'tajwid_score' => $data['tajwid_score'] ?? null,
            'fluency_score' => $data['fluency_score'] ?? null,
            'result' => QuranAssessment::resultForScores(
                $data['fasohah_score'] ?? null, $data['tajwid_score'] ?? null,
                $data['makhraj_score'] ?? null, $data['fluency_score'] ?? null,
            ),
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function studyProgramName(int $studyProgramId): string
    {
        $studyProgram = StudyProgram::query()->lockForUpdate()->find($studyProgramId);
        if ($studyProgram === null) {
            throw ValidationException::withMessages([
                'study_program_id' => 'Program studi tidak lagi tersedia. Pilih program studi yang tersedia di Master Data.',
            ]);
        }

        $name = $studyProgram->getAttribute('name');
        if (! is_string($name)) {
            throw ValidationException::withMessages(['study_program_id' => 'Nama program studi tidak valid. Periksa Master Data.']);
        }

        return $name;
    }

    /** @param array<string, mixed> $props */
    private function privatePage(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F\x{FEFF}\x{200B}]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }
}
