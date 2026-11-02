<?php

use App\Actions\Interviews\InterviewAnswerTable;
use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\StudyProgram;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function pmbSessionDetails(array $overrides = []): array
{
    return [...['wave' => 2, 'period' => '2027/2028', 'campus' => 'C'], ...$overrides];
}

function pmbSessionFormPayload(string $kind): array
{
    if ($kind === 'quran-tests') {
        return pmbSessionGradePayload(pmbSessionDetails());
    }
    $common = ['title' => 'Tes Penerimaan Mahasiswa Baru', 'is_open' => true, ...pmbSessionDetails()];

    return $kind === 'quran-exams'
        ? [...$common, 'assessment_date' => '2026-10-30']
        : [...$common, 'questions' => [['id' => (string) Str::uuid(), 'prompt' => 'Apa alasan memilih UNUHA?', 'required' => true]]];
}

function pmbSessionInterview(User $owner, array $overrides = []): InterviewForm
{
    return InterviewForm::query()->create([
        ...pmbSessionFormPayload('interviews'), 'created_by' => $owner->id,
        'public_token' => Str::random(64), 'questions_version' => (string) Str::uuid(), ...$overrides,
    ]);
}

function pmbSessionGradePayload(array $overrides = []): array
{
    $program = StudyProgram::query()->firstOrCreate(['identity_key' => StudyProgram::identityKey('Informatika')], ['name' => 'Informatika']);

    return [
        'name' => 'Peserta Uji', 'registration_number' => '000123', 'study_program_id' => $program->id,
        'academic_year' => '2027/2028', 'retain_study_program' => false, 'assessment_date' => '2026-10-30',
        'examiner_name' => 'Penguji Uji', 'reading_reference' => 'Al-Fatihah 1-7',
        'fasohah_score' => 70, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70,
        'submission_token' => (string) Str::uuid(), ...$overrides,
    ];
}

function pmbSessionCsv(string $content): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

test('admin saves and edits session details visible to guests and in form lists', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true]);
    $data = pmbSessionFormPayload($kind);
    $this->actingAs($owner)->post(route($kind.'.store'), [...$data, 'period' => '  2027/2028  '])->assertSessionHasNoErrors()->assertRedirect();
    $form = $kind === 'quran-exams' ? QuranExam::query()->sole() : InterviewForm::query()->sole();
    $this->assertDatabaseHas($form->getTable(), ['id' => $form->id, ...pmbSessionDetails()]);
    $prop = $kind === 'quran-exams' ? 'exam' : 'interview';
    $collection = $kind === 'quran-exams' ? 'exams' : 'interviews';
    $this->get(route($kind.'.show', $form))->assertInertia(fn (Assert $page) => $page->where($prop.'.wave', 2)->where($prop.'.period', '2027/2028')->where($prop.'.campus', 'C'));
    $this->get(route($kind.'.index'))->assertInertia(fn (Assert $page) => $page->where($collection.'.data.0.wave', 2)->where($collection.'.data.0.campus', 'C'));
    $version = $kind === 'quran-exams' ? ['version' => $form->version] : ['questions_version' => $form->questions_version];
    $this->put(route($kind.'.update', $form), [...$data, ...$version, 'wave' => 3, 'campus' => 'B', 'period' => 'Periode khusus Januari 2028'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas($form->getTable(), ['id' => $form->id, 'wave' => 3, 'campus' => 'B', 'period' => 'Periode khusus Januari 2028']);
    auth()->logout();
    $this->get(route($kind.'.join', $form->public_token))->assertOk()->assertInertia(fn (Assert $page) => $page->where($prop.'.wave', 3)->where($prop.'.period', 'Periode khusus Januari 2028')->where($prop.'.campus', 'B')->where('auth.user', null));
})->with(['quran-exams', 'interviews']);

test('session fields reject unavailable categories and invalid manual periods', function (string $kind, string $field, mixed $value, string $message) {
    $owner = User::factory()->create(['is_active' => true]);
    $this->actingAs($owner)->post(route($kind.'.store'), [...pmbSessionFormPayload($kind), $field => $value])->assertSessionHasErrors([$field => $message]);
    $this->assertDatabaseCount(match ($kind) {
        'quran-exams' => 'quran_exams',
        'quran-tests' => 'quran_assessments',
        default => 'interview_forms',
    }, 0);
})->with(['quran-exams', 'interviews', 'quran-tests'])->with([
    'zero wave' => ['wave', 0, 'Pilih Gelombang 1, 2, atau 3.'],
    'fourth wave' => ['wave', 4, 'Pilih Gelombang 1, 2, atau 3.'],
    'fractional wave' => ['wave', 1.5, 'Pilih Gelombang 1, 2, atau 3.'],
    'unavailable campus' => ['campus', 'A', 'Pilih Kampus B atau Kampus C.'],
    'period over limit' => ['period', str_repeat('x', 101), 'Periode maksimal 100 karakter.'],
    'period is array' => ['period', ['2027/2028'], 'Periode harus berupa teks.'],
]);

test('old activities remain editable without inventing missing session details', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true]);
    $data = pmbSessionFormPayload($kind);
    unset($data['wave'], $data['period'], $data['campus']);
    $this->actingAs($owner)->post(route($kind.'.store'), $data)->assertSessionHasNoErrors();
    $form = $kind === 'quran-exams' ? QuranExam::query()->sole() : InterviewForm::query()->sole();
    $this->assertDatabaseHas($form->getTable(), ['id' => $form->id, 'wave' => null, 'period' => null, 'campus' => null]);
    $version = $kind === 'quran-exams' ? ['version' => $form->version] : ['questions_version' => $form->questions_version];
    $this->put(route($kind.'.update', $form), [...$data, ...$version, 'is_open' => false])->assertSessionHasNoErrors();
    $this->assertDatabaseHas($form->getTable(), ['id' => $form->id, 'is_open' => false, 'wave' => null, 'period' => null, 'campus' => null]);
})->with(['quran-exams', 'interviews']);

test('session details cannot be changed by another account', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);
    $form = $kind === 'quran-exams' ? QuranExam::factory()->create(['created_by' => $owner->id, ...pmbSessionDetails()]) : pmbSessionInterview($owner);
    $version = $kind === 'quran-exams' ? ['version' => $form->version] : ['questions_version' => $form->questions_version];
    $this->actingAs($other)->put(route($kind.'.update', $form), [...pmbSessionFormPayload($kind), ...$version, 'wave' => 1])->assertForbidden();
    $this->assertDatabaseHas($form->getTable(), ['id' => $form->id, ...pmbSessionDetails()]);
})->with(['quran-exams', 'interviews']);

test('manual Quran assessments retain editable session information in details and print', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $data = pmbSessionGradePayload(pmbSessionDetails());
    $this->actingAs($owner)->post(route('quran-tests.store'), $data)->assertSessionHasNoErrors();
    $assessment = QuranAssessment::query()->sole();
    $this->assertDatabaseHas('quran_assessments', ['id' => $assessment->id, ...pmbSessionDetails()]);
    unset($data['submission_token']);
    $this->put(route('quran-tests.update', $assessment), [...$data, 'version' => $assessment->version, 'wave' => 1, 'campus' => 'B'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('quran_assessments', ['id' => $assessment->id, 'wave' => 1, 'campus' => 'B']);
    $this->get(route('quran-tests.show', $assessment))->assertInertia(fn (Assert $page) => $page->where('assessment.wave', 1)->where('assessment.period', '2027/2028')->where('assessment.campus', 'B'));
    $this->get(route('quran-tests.print', $assessment))->assertOk()->assertSee('Gelombang 1')->assertSee('2027/2028')->assertSee('Kampus B');
});

test('public Quran scores use examiner session input and allow admin corrections', function () {
    $exam = QuranExam::factory()->create(pmbSessionDetails());
    $payload = pmbSessionGradePayload(['exam_version' => $exam->version, 'wave' => 1, 'period' => 'Palsu', 'campus' => 'B']);
    $this->post(route('quran-exams.submit', $exam->public_token), $payload)->assertSessionHasNoErrors();
    $row = QuranAssessment::query()->sole();
    $this->assertDatabaseHas('quran_assessments', ['id' => $row->id, 'wave' => 1, 'period' => 'Palsu', 'campus' => 'B']);
    unset($payload['submission_token']);
    $this->actingAs($exam->creator)->put(route('quran-tests.update', $row), [...$payload, 'version' => $row->version])->assertSessionHasNoErrors();
    $this->get(route('quran-tests.show', $row))->assertInertia(fn (Assert $page) => $page->where('assessment.wave', 1)->where('assessment.campus', 'B')->where('assessment.period', 'Palsu'));
});

test('Quran CSV and PDF contain session data for every participant past pagination', function () {
    $exam = QuranExam::factory()->create(pmbSessionDetails());
    foreach (range(1, 24) as $number) {
        $exam->assessments()->create([
            'name' => 'Peserta '.sprintf('%03d', $number), 'registration_number' => sprintf('%06d', $number),
            'study_program' => 'Pendidikan Bahasa dan Sastra Indonesia', 'assessment_date' => '2026-10-30',
            'examiner_name' => 'Dr. Penguji Contoh, M.Pd.', 'reading_reference' => 'Al-Fatihah 1-7',
            'fasohah_score' => 70, 'tajwid_score' => 80, 'makhraj_score' => 90, 'fluency_score' => 80,
            'result' => 'mq2', 'created_by' => $exam->created_by, 'version' => (string) Str::uuid(), 'submission_token' => (string) Str::uuid(),
        ]);
    }
    $this->actingAs($exam->creator);
    $csv = $this->get(route('quran-tests.export', ['exam_id' => $exam->id, 'page' => 2]))->assertOk();
    $rows = pmbSessionCsv($csv->streamedContent());
    expect($rows)->toHaveCount(25);
    $last = array_combine($rows[0], $rows[24]);
    expect($last['Nama'])->toBe('Peserta 024')->and($last['Gelombang'])->toBe('Gelombang 2')->and($last['Periode'])->toBe('')->and($last['Tahun Akademik'])->toBe('2027/2028')->and($last['Lokasi Kampus'])->toBe('Kampus C');
    $html = view('quran-tests.report', ['rows' => $exam->assessments()->with('exam')->get(), 'scope' => $exam->title, 'filters' => [], 'exportedAt' => now(), 'letterhead' => 'data:image/png;base64,'])->render();
    expect($html)->toContain('Gelombang 2', '2027/2028', 'Kampus C', 'Peserta 024');
    $pdf = $this->get(route('quran-tests.pdf', ['exam_id' => $exam->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    if ($path = getenv('SISURAT_SESSION_QURAN_PDF')) {
        file_put_contents($path, $pdf->getContent());
    }
});

test('interview recaps preserve all answers and include escaped session details', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $form = pmbSessionInterview($owner, ['period' => '=2027/2028 <script>bad()</script>']);
    foreach (range(1, 23) as $number) {
        $form->submissions()->create([
            'name' => 'Peserta '.sprintf('%03d', $number), 'registration_number' => sprintf('%06d', $number),
            'registration_key' => InterviewSubmission::registrationKey((string) $number), 'study_program' => 'Informatika',
            'submitted_at' => now(), 'submission_token' => (string) Str::uuid(),
            'answers' => [['question_id' => $form->questions[0]['id'], 'question' => 'Apa alasan memilih UNUHA?', 'required' => true, 'answer' => 'Jawaban lengkap peserta '.$number]],
        ]);
    }
    $this->actingAs($owner);
    $csv = $this->get(route('interviews.export', ['interview' => $form, 'page' => 2]))->assertOk();
    $rows = pmbSessionCsv($csv->streamedContent());
    expect($rows)->toHaveCount(24);
    $last = array_combine($rows[0], $rows[23]);
    expect($last['Nama Peserta'])->toBe('Peserta 023')->and($last['Gelombang'])->toBe('Gelombang 2')->and($last['Periode'])->toBe("'=2027/2028 <script>bad()</script>")->and($last['Lokasi Kampus'])->toBe('Kampus C');
    $html = view('interviews.report', [
        'interview' => $form, 'submissions' => $form->submissions()->get(), 'exportedAt' => now(),
        'answerTable' => new InterviewAnswerTable((new Dompdf)->getFontMetrics()), 'letterhead' => 'data:image/png;base64,',
    ])->render();
    expect($html)->toContain('Gelombang 2', '2027/2028 &lt;script&gt;', 'Kampus C', 'Jawaban lengkap peserta 23')->not->toContain('<script>bad()</script>');
    $form->update(['period' => '2027/2028']);
    $pdf = $this->get(route('interviews.pdf', $form))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    if ($path = getenv('SISURAT_SESSION_INTERVIEW_PDF')) {
        file_put_contents($path, $pdf->getContent());
    }
});
