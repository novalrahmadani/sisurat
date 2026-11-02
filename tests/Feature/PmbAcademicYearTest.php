<?php

use App\Models\InterviewForm;
use App\Models\InterviewSubmission;
use App\Models\QuranExam;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

function academicYearActivity(string $kind): QuranExam|InterviewForm
{
    $owner = User::factory()->create(['is_active' => true]);
    $legacy = ['created_by' => $owner->id, 'wave' => 1, 'period' => '2024/2025', 'campus' => 'B'];

    return $kind === 'quran-exams'
        ? QuranExam::factory()->create([...$legacy, 'is_open' => true])
        : InterviewForm::query()->create([
            ...$legacy, 'title' => 'Wawancara PMB', 'is_open' => true,
            'questions' => [['id' => (string) Str::uuid(), 'prompt' => 'Mengapa memilih UNUHA?', 'required' => true]],
            'questions_version' => (string) Str::uuid(), 'public_token' => Str::random(64),
        ]);
}

function academicYearPayload(QuranExam|InterviewForm $activity, array $overrides = []): array
{
    $program = StudyProgram::query()->firstOrCreate(['identity_key' => StudyProgram::identityKey('Informatika')], ['name' => 'Informatika']);
    $common = ['wave' => 2, 'period' => '1', 'academic_year' => '2027/2028', 'campus' => 'C',
        'name' => 'Peserta Pertama', 'registration_number' => '000123', 'study_program_id' => $program->id,
        'submission_token' => (string) Str::uuid()];
    $specific = $activity instanceof QuranExam
        ? ['exam_version' => $activity->version, 'examiner_name' => 'Penguji Contoh', 'reading_reference' => 'Al-Fatihah 1-7',
            'fasohah_score' => 70, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70]
        : ['questions_version' => $activity->questions_version,
            'answers' => [['question_id' => $activity->questions[0]['id'], 'answer' => 'Ingin belajar dan mengembangkan kemampuan.']]];

    return [...$common, ...$specific, ...$overrides];
}

function academicYearCsvRows(string $content): array
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, substr($content, 3));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

test('guests save independent period and academic year values visible in results and complete recaps', function (string $kind) {
    $activity = academicYearActivity($kind);
    $payload = academicYearPayload($activity, ['academic_year' => '  2027/2028  ']);
    $this->post(route($kind.'.submit', $activity->public_token), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route($kind.'.submit', $activity->public_token), academicYearPayload($activity, [
        'name' => 'Peserta Kedua', 'registration_number' => '000456', 'period' => '2', 'academic_year' => '2028/2029',
    ]))->assertSessionHasNoErrors();
    $table = $activity instanceof QuranExam ? 'quran_assessments' : 'interview_submissions';
    $this->assertDatabaseHas($table, ['registration_number' => '000123', 'period' => '1', 'academic_year' => '2027/2028']);
    $this->assertDatabaseHas($table, ['registration_number' => '000456', 'period' => '2', 'academic_year' => '2028/2029']);
    $this->actingAs($activity->creator);
    if ($activity instanceof QuranExam) {
        $record = $activity->assessments()->where('registration_number', '000123')->firstOrFail();
        $this->get(route('quran-tests.show', $record))->assertInertia(fn (Assert $page) => $page->where('assessment.period', '1')->where('assessment.academic_year', '2027/2028'));
        $this->get(route('quran-tests.index'))->assertInertia(fn (Assert $page) => $page->where('assessments.data.0.academic_year', '2028/2029'));
        $this->get(route('quran-tests.print', $record))->assertOk()->assertSee('Tahun Akademik')->assertSee('2027/2028');
        $csv = $this->get(route('quran-tests.export', ['exam_id' => $activity->id]));
        $pdf = $this->get(route('quran-tests.pdf', ['exam_id' => $activity->id]));
        $artifact = getenv('SISURAT_ACADEMIC_QURAN_PDF');
    } else {
        $record = $activity->submissions()->where('registration_number', '000123')->firstOrFail();
        $this->get(route('interviews.submissions.show', [$activity, $record]))->assertInertia(fn (Assert $page) => $page->where('submission.period', '1')->where('submission.academic_year', '2027/2028'));
        $this->get(route('interviews.show', $activity))->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.academic_year', '2028/2029'));
        $this->get(route('interviews.submissions.print', [$activity, $record]))->assertOk()->assertSee('Tahun Akademik')->assertSee('2027/2028');
        $csv = $this->get(route('interviews.export', $activity));
        $pdf = $this->get(route('interviews.pdf', $activity));
        $artifact = getenv('SISURAT_ACADEMIC_INTERVIEW_PDF');
    }
    $csv->assertOk();
    $rows = academicYearCsvRows($csv->streamedContent());
    expect($rows)->toHaveCount(3);
    $first = array_combine($rows[0], $rows[1]);
    $second = array_combine($rows[0], $rows[2]);
    expect($first['Periode'])->toBe('1');
    expect($first['Tahun Akademik'])->toBe('2027/2028');
    expect($second['Periode'])->toBe('2');
    expect($second['Tahun Akademik'])->toBe('2028/2029');
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    if (is_string($artifact) && $artifact !== '') {
        file_put_contents($artifact, $pdf->getContent());
    }
})->with(['quran-exams', 'interviews']);

test('public academic year input rejects missing malformed and non-text values with clear messages', function (string $kind, mixed $value, string $message) {
    $activity = academicYearActivity($kind);
    $this->post(route($kind.'.submit', $activity->public_token), academicYearPayload($activity, ['academic_year' => $value]))->assertSessionHasErrors(['academic_year' => $message]);
    $this->assertDatabaseCount($activity instanceof QuranExam ? 'quran_assessments' : 'interview_submissions', 0);
})->with(['quran-exams', 'interviews'])->with([
    'missing year' => [null, 'Tahun akademik wajib diisi.'],
    'invalid format' => ['2027/28', 'Gunakan format tahun akademik, misalnya 2027/2028.'],
    'excessive length' => ['2027/20280', 'Gunakan format tahun akademik, misalnya 2027/2028.'],
    'array instead of text' => [['2027/2028'], 'Tahun akademik harus berupa teks.'],
]);

test('legacy academic years are displayed separately without overwriting original records', function (string $kind) {
    $activity = academicYearActivity($kind);
    $record = $activity instanceof QuranExam
        ? $activity->assessments()->create(['created_by' => $activity->created_by, 'name' => 'Peserta Lama', 'registration_number' => '000001', 'study_program' => 'Informatika', 'assessment_date' => '2026-10-30', 'result' => 'pending', 'version' => (string) Str::uuid(), 'submission_token' => (string) Str::uuid()])
        : $activity->submissions()->create(['name' => 'Peserta Lama', 'registration_number' => '000001', 'registration_key' => InterviewSubmission::registrationKey('000001'), 'study_program' => 'Informatika', 'answers' => [], 'submitted_at' => now(), 'submission_token' => (string) Str::uuid()]);
    $this->actingAs($activity->creator);
    if ($activity instanceof QuranExam) {
        $this->get(route('quran-tests.edit', $record))->assertInertia(fn (Assert $page) => $page->where('assessment.period', null)->where('assessment.academic_year', '2024/2025'));
    } else {
        $this->get(route('interviews.submissions.show', [$activity, $record]))->assertInertia(fn (Assert $page) => $page->where('submission.period', null)->where('submission.academic_year', '2024/2025'));
    }
    $this->assertDatabaseHas($activity->getTable(), ['id' => $activity->id, 'period' => '2024/2025']);
    $this->assertDatabaseHas($record->getTable(), ['id' => $record->id, 'period' => null, 'academic_year' => null]);
})->with(['quran-exams', 'interviews']);

test('the owner can correct the period and academic year on an existing Quran result', function () {
    $activity = academicYearActivity('quran-exams');
    $payload = academicYearPayload($activity);
    $this->post(route('quran-exams.submit', $activity->public_token), $payload)->assertSessionHasNoErrors();
    $record = $activity->assessments()->sole();
    unset($payload['submission_token']);
    $this->actingAs($activity->creator)->put(route('quran-tests.update', $record), [
        ...$payload, 'version' => $record->version, 'assessment_date' => $record->assessment_date->format('Y-m-d'),
        'retain_study_program' => true, 'period' => '2', 'academic_year' => '2028/2029',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('quran_assessments', ['id' => $record->id, 'period' => '2', 'academic_year' => '2028/2029']);
    $record->refresh();
    $this->put(route('quran-tests.update', $record), [
        ...$payload, 'version' => $record->version, 'assessment_date' => $record->assessment_date->format('Y-m-d'),
        'retain_study_program' => true, 'period' => '', 'academic_year' => '2028/2029',
    ])->assertSessionHasNoErrors();
    $this->get(route('quran-tests.show', $record))->assertInertia(fn (Assert $page) => $page->where('assessment.period', null)->where('assessment.academic_year', '2028/2029'));
});
