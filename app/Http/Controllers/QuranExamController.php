<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuranExamRequest;
use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\User;
use App\Support\PublicFormLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class QuranExamController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'quran-exams/Index', ['exams' => QuranExam::query()->where('created_by', $request->user()->id)
            ->withCount('assessments')->orderByDesc('assessment_date')->orderByDesc('id')->paginate(15)->through(fn (QuranExam $exam): array => $this->examData($exam))]);
    }

    public function create(Request $request): Response
    {
        return $this->page($request, 'quran-exams/Create', ['today' => now('Asia/Jakarta')->format('Y-m-d')]);
    }

    public function store(QuranExamRequest $request): RedirectResponse
    {
        $exam = DB::transaction(function () use ($request): QuranExam {
            $owner = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($owner->is_active, 403);

            return QuranExam::query()->create([...$request->validated(), 'created_by' => $owner->id,
                'public_token' => Str::random(64), 'version' => (string) Str::uuid()]);
        }, 5);

        return to_route('quran-exams.show', ['quran_exam' => $exam->id]);
    }

    public function show(Request $request, QuranExam $quranExam, PublicFormLinks $links): Response
    {
        Gate::authorize('view', $quranExam);
        $quranExam->loadCount('assessments');

        return $this->page($request, 'quran-exams/Show', [
            'exam' => [...$this->examData($quranExam), 'description' => $quranExam->description, 'version' => $quranExam->version],
            'publicUrl' => $links->url('quran-exams.join', ['token' => $quranExam->public_token]),
            'assessments' => $quranExam->assessments()->orderByDesc('id')->paginate(20)->through(fn (QuranAssessment $row): array => [
                ...$row->only(['id', 'name', 'registration_number', 'study_program', 'examiner_name', 'result']),
                'average_score' => $row->averageScore(),
            ]),
        ]);
    }

    public function update(QuranExamRequest $request, QuranExam $quranExam): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($quranExam, $data): void {
            $exam = QuranExam::query()->lockForUpdate()->findOrFail($quranExam->id);
            Gate::authorize('update', $exam);
            if ($data['version'] !== $exam->version) {
                throw ValidationException::withMessages(['version' => 'Pengaturan berubah dari halaman lain. Muat ulang sebelum menyimpan.']);
            }
            if ($data['assessment_date'] !== $exam->assessment_date->format('Y-m-d') && $exam->assessments()->exists()) {
                throw ValidationException::withMessages(['assessment_date' => 'Tanggal tes sudah digunakan untuk penilaian. Buat kegiatan tes baru untuk tanggal lain.']);
            }
            $exam->update([...$data, 'version' => (string) Str::uuid()]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan tautan penguji disimpan.']);

        return to_route('quran-exams.show', ['quran_exam' => $quranExam->id]);
    }

    /** @return array<string, mixed> */
    private function examData(QuranExam $exam): array
    {
        return [...$exam->only(['id', 'title', 'wave', 'period', 'campus', 'is_open', 'assessments_count']), 'assessment_date' => $exam->assessment_date->format('Y-m-d')];
    }

    /** @param array<string, mixed> $props */
    private function page(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->add(['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);

        return $response;
    }
}
