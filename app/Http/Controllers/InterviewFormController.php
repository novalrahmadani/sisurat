<?php

namespace App\Http\Controllers;

use App\Http\Requests\InterviewFormRequest;
use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use App\Support\PublicFormLinks;
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

class InterviewFormController extends Controller
{
    public function __construct(private PublicFormLinks $links) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $creatorId = (int) $request->user()->id;
        $interviews = $this->formsQuery($creatorId, $search, $status)
            ->select(['id', 'title', 'description', 'wave', 'period', 'campus', 'is_open', 'questions', 'created_at'])
            ->withCount('submissions')->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn (InterviewForm $interview): array => [
                ...$interview->only(['id', 'title', 'description', 'wave', 'period', 'campus', 'is_open', 'submissions_count']),
                'questions_count' => count($interview->questions),
                'created_at' => $interview->created_at->toIso8601String(),
            ]);

        return $this->privatePage($request, 'interviews/Index', [
            'interviews' => $interviews,
            'filters' => ['q' => $search, 'status' => $status],
            'stats' => [
                'forms' => InterviewForm::query()->where('created_by', $creatorId)->count(),
                'open' => InterviewForm::query()->where('created_by', $creatorId)->where('is_open', true)->count(),
                'submissions' => InterviewSubmission::query()
                    ->whereHas('form', fn (Builder $query) => $query->where('created_by', $creatorId))->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $prompts = [
            'Apa alasan Anda memilih Universitas Nurul Huda?',
            'Mengapa Anda memilih program studi tersebut?',
            'Ceritakan minat, pengalaman, atau kegiatan yang mendukung pilihan studi Anda.',
            'Bagaimana rencana Anda mengatur waktu dan komitmen selama kuliah?',
            'Apa harapan dan tujuan yang ingin Anda capai selama kuliah?',
        ];

        return $this->privatePage($request, 'interviews/Form', [
            'interview' => null,
            'defaultQuestions' => array_map(fn (string $prompt): array => [
                'id' => (string) Str::uuid(),
                'prompt' => $prompt,
                'required' => true,
            ], $prompts),
        ]);
    }

    public function store(InterviewFormRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $interview = InterviewForm::query()->create([
            ...Arr::only($data, ['wave', 'period', 'campus']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_open' => (bool) $data['is_open'],
            'questions' => $this->normalizedQuestions($data['questions']),
            'questions_version' => (string) Str::uuid(),
            'public_token' => Str::random(64),
            'created_by' => $request->user()->id,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form wawancara berhasil dibuat. Bagikan tautannya kepada mahasiswa baru.']);

        return to_route('interviews.show', $interview);
    }

    public function show(Request $request, InterviewForm $interview): Response
    {
        Gate::authorize('view', $interview);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['q'] ?? '');
        $submissions = $this->submissionsQuery($interview, $search)
            ->with('form')->select(['id', 'interview_form_id', 'wave', 'period', 'academic_year', 'campus', 'name', 'registration_number', 'study_program', 'submitted_at'])
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (InterviewSubmission $submission): array => $this->submissionSummary($submission));

        return $this->privatePage($request, 'interviews/Show', [
            'interview' => $this->formData($interview),
            'submissions' => $submissions,
            'filters' => ['q' => $search],
            'public_url' => $this->links->url('interviews.join', ['token' => $interview->public_token]),
        ]);
    }

    public function edit(Request $request, InterviewForm $interview): Response
    {
        Gate::authorize('update', $interview);

        return $this->privatePage($request, 'interviews/Form', ['interview' => $this->formData($interview)]);
    }

    public function update(InterviewFormRequest $request, InterviewForm $interview): RedirectResponse
    {
        DB::transaction(function () use ($request, $interview): void {
            $lockedForm = InterviewForm::query()->lockForUpdate()->findOrFail($interview->id);
            Gate::authorize('update', $lockedForm);
            $data = $request->validated();
            if ($data['questions_version'] !== $lockedForm->questions_version) {
                throw ValidationException::withMessages([
                    'questions_version' => 'Pertanyaan telah diubah dari halaman lain. Muat ulang form lalu periksa perubahan sebelum menyimpan kembali.',
                ]);
            }
            $questions = $this->normalizedQuestions($data['questions']);
            $lockedForm->update([
                ...Arr::only($data, ['wave', 'period', 'campus']),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_open' => (bool) $data['is_open'],
                'questions' => $questions,
                'questions_version' => $questions !== $lockedForm->questions ? (string) Str::uuid() : $lockedForm->questions_version,
            ]);
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form dan pertanyaan wawancara berhasil diperbarui. Jawaban yang sudah masuk tetap tersimpan dengan pertanyaan semula.']);

        return to_route('interviews.show', $interview);
    }

    public function destroy(Request $request, InterviewForm $interview): RedirectResponse
    {
        Gate::authorize('delete', $interview);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($interview): void {
            $lockedForm = InterviewForm::query()->lockForUpdate()->findOrFail($interview->id);
            Gate::authorize('delete', $lockedForm);
            $lockedForm->delete();
        }, 5);
        $remaining = $this->formsQuery((int) $request->user()->id, trim($filters['q'] ?? ''), $filters['status'] ?? 'all')->count();
        $filters['page'] = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($remaining / 15)));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Form wawancara beserta seluruh jawabannya berhasil dihapus.']);

        return to_route('interviews.index', $filters);
    }

    public function showSubmission(Request $request, InterviewForm $interview, InterviewSubmission $submission): Response
    {
        Gate::authorize('view', $interview);
        abort_unless((int) $submission->interview_form_id === (int) $interview->id, 404);

        return $this->privatePage($request, 'interviews/Submission', [
            'interview' => $interview->only(['id', 'title', 'wave', 'period', 'campus']),
            'submission' => [...$this->submissionSummary($submission), 'answers' => $submission->answers],
        ]);
    }

    public function printSubmission(InterviewForm $interview, InterviewSubmission $submission): HttpResponse
    {
        Gate::authorize('view', $interview);
        abort_unless((int) $submission->interview_form_id === (int) $interview->id, 404);

        return response()->view('interviews.submission', [
            'interview' => $interview,
            'submission' => $submission,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function destroySubmission(Request $request, InterviewForm $interview, InterviewSubmission $submission): RedirectResponse
    {
        Gate::authorize('delete', $interview);
        abort_unless((int) $submission->interview_form_id === (int) $interview->id, 404);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($interview, $submission): void {
            $lockedForm = InterviewForm::query()->lockForUpdate()->findOrFail($interview->id);
            Gate::authorize('delete', $lockedForm);
            $lockedForm->submissions()->whereKey($submission->id)->lockForUpdate()->firstOrFail()->delete();
        }, 5);
        $remaining = $this->submissionsQuery($interview, trim($filters['q'] ?? ''))->count();
        $filters['page'] = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($remaining / 20)));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jawaban wawancara mahasiswa berhasil dihapus.']);

        return to_route('interviews.show', ['interview' => $interview->id, ...$filters]);
    }

    /** @return Builder<InterviewForm> */
    private function formsQuery(int $creatorId, string $search, string $status): Builder
    {
        return InterviewForm::query()->where('created_by', $creatorId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('is_open', $status === 'open'));
    }

    /** @return Builder<InterviewSubmission> */
    private function submissionsQuery(InterviewForm $interview, string $search): Builder
    {
        return $interview->submissions()->getQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('registration_number', 'like', '%'.$search.'%')
                        ->orWhere('study_program', 'like', '%'.$search.'%');
                });
            });
    }

    /** @return array<string, mixed> */
    private function formData(InterviewForm $interview): array
    {
        return [
            ...$interview->only(['id', 'title', 'description', 'wave', 'period', 'campus', 'is_open', 'questions', 'questions_version']),
            'submissions_count' => $interview->submissions()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function submissionSummary(InterviewSubmission $submission): array
    {
        return [
            ...$submission->only(['id', 'name', 'registration_number', 'study_program']),
            ...$submission->testSessionDetails(),
            'submitted_at' => $submission->submitted_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{id: string, prompt: string, required: bool|int|string}>  $questions
     * @return array<int, array{id: string, prompt: string, required: bool}>
     */
    private function normalizedQuestions(array $questions): array
    {
        return array_map(fn (array $question): array => [
            'id' => strtolower($question['id']),
            'prompt' => $question['prompt'],
            'required' => (bool) $question['required'],
        ], $questions);
    }

    /** @param array<string, mixed> $props */
    private function privatePage(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
