<?php

use App\Actions\Interviews\InterviewAnswerTable;
use App\Models\InterviewForm;
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

function respondentActivity(string $kind): QuranExam|InterviewForm
{
    $owner = User::factory()->create(['is_active' => true]);
    $legacy = ['created_by' => $owner->id, 'wave' => 1, 'period' => '2020/2021', 'campus' => 'B'];

    return $kind === 'quran-exams'
        ? QuranExam::factory()->create($legacy)
        : InterviewForm::query()->create([
            ...$legacy, 'title' => 'Wawancara PMB', 'is_open' => true,
            'questions' => [['id' => (string) Str::uuid(), 'prompt' => 'Mengapa memilih UNUHA?', 'required' => true]],
            'questions_version' => (string) Str::uuid(), 'public_token' => Str::random(64),
        ]);
}

function respondentPayload(QuranExam|InterviewForm $form, array $overrides = []): array
{
    $program = StudyProgram::query()->firstOrCreate(['identity_key' => StudyProgram::identityKey('Informatika')], ['name' => 'Informatika']);
    $common = ['name' => 'Peserta Contoh', 'registration_number' => '000123', 'study_program_id' => $program->id,
        'wave' => 2, 'period' => '2027/2028', 'academic_year' => '2027/2028', 'campus' => 'C', 'submission_token' => (string) Str::uuid()];
    $specific = $form instanceof QuranExam
        ? ['exam_version' => $form->version, 'examiner_name' => 'Penguji', 'reading_reference' => 'Al-Fatihah 1-7',
            'fasohah_score' => 70, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70]
        : ['questions_version' => $form->questions_version,
            'answers' => [['question_id' => $form->questions[0]['id'], 'answer' => 'Ingin belajar dan mengembangkan kemampuan.']]];

    return [...$common, ...$specific, ...$overrides];
}

function respondentCsvRows(string $content): array
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

test('guest examiner and participant save their own session details independently of activity settings', function (string $kind) {
    $activity = respondentActivity($kind);
    $first = respondentPayload($activity, ['period' => '  2027/2028  ']);
    $this->post(route($kind.'.submit', $activity->public_token), $first)->assertSessionHasNoErrors()->assertRedirect();
    $second = respondentPayload($activity, ['name' => 'Peserta Kedua', 'registration_number' => '000456', 'wave' => 3, 'period' => '2028/2029', 'campus' => 'B']);
    $this->post(route($kind.'.submit', $activity->public_token), $second)->assertSessionHasNoErrors();
    $table = $kind === 'quran-exams' ? 'quran_assessments' : 'interview_submissions';
    $this->assertDatabaseHas($table, ['registration_number' => '000123', 'wave' => 2, 'period' => '2027/2028', 'campus' => 'C']);
    $this->assertDatabaseHas($table, ['registration_number' => '000456', 'wave' => 3, 'period' => '2028/2029', 'campus' => 'B']);
    $this->post(route($kind.'.submit', $activity->public_token), [...$first, 'wave' => 1, 'period' => 'Tidak boleh menimpa', 'campus' => 'B'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount($table, 2);
    $activity->update(['wave' => 3, 'period' => '2030/2031', 'campus' => 'B']);
    $this->actingAs($activity->creator);
    if ($activity instanceof QuranExam) {
        $row = $activity->assessments()->where('registration_number', '000123')->firstOrFail();
        $this->get(route('quran-tests.show', $row))->assertInertia(fn (Assert $page) => $page->where('assessment.wave', 2)->where('assessment.period', '2027/2028')->where('assessment.campus', 'C'));
        $this->get(route('quran-tests.print', $row))->assertOk()->assertSee('Berhak mengikuti MQ 2')->assertDontSee('lulus MQ 2');
    } else {
        $row = $activity->submissions()->where('registration_number', '000123')->firstOrFail();
        $this->get(route('interviews.submissions.show', [$activity, $row]))->assertInertia(fn (Assert $page) => $page->where('submission.wave', 2)->where('submission.period', '2027/2028')->where('submission.campus', 'C'));
        $this->get(route('interviews.submissions.print', [$activity, $row]))->assertOk()->assertSee('Gelombang 2')->assertSee('2027/2028')->assertSee('Kampus C')->assertDontSee('2030/2031');
    }
})->with(['quran-exams', 'interviews']);

test('public session fields are required and reject unavailable values', function (string $kind, string $field, mixed $value, string $message) {
    $activity = respondentActivity($kind);
    $this->post(route($kind.'.submit', $activity->public_token), respondentPayload($activity, [$field => $value]))->assertSessionHasErrors([$field => $message]);
    $this->assertDatabaseCount($kind === 'quran-exams' ? 'quran_assessments' : 'interview_submissions', 0);
})->with(['quran-exams', 'interviews'])->with([
    'missing wave' => ['wave', null, 'Pilih gelombang tes.'],
    'missing period' => ['period', '   ', 'Periode tes wajib diisi.'],
    'missing campus' => ['campus', null, 'Pilih lokasi kampus.'],
    'unavailable wave' => ['wave', 4, 'Pilih Gelombang 1, 2, atau 3.'],
    'fractional wave' => ['wave', 1.5, 'Pilih Gelombang 1, 2, atau 3.'],
    'unavailable campus' => ['campus', 'A', 'Pilih Kampus B atau Kampus C.'],
    'period too long' => ['period', str_repeat('x', 101), 'Periode maksimal 100 karakter.'],
    'period not text' => ['period', ['2027'], 'Periode harus berupa teks.'],
]);

test('CSV and PDF recaps use each respondents session and protect typed period text', function (string $kind) {
    $activity = respondentActivity($kind);
    $first = respondentPayload($activity, ['period' => '=2027/2028 <script>bad()</script>']);
    $second = respondentPayload($activity, ['name' => 'Peserta Kedua', 'registration_number' => '000456', 'wave' => 3, 'period' => '2028/2029', 'campus' => 'B']);
    $this->post(route($kind.'.submit', $activity->public_token), $first)->assertSessionHasNoErrors();
    $this->post(route($kind.'.submit', $activity->public_token), $second)->assertSessionHasNoErrors();
    $this->actingAs($activity->creator);
    $response = $activity instanceof QuranExam
        ? $this->get(route('quran-tests.export', ['exam_id' => $activity->id]))
        : $this->get(route('interviews.export', $activity));
    $response->assertOk();
    $rows = respondentCsvRows($response->streamedContent());
    expect($rows)->toHaveCount(3);
    $firstRow = array_combine($rows[0], $rows[1]);
    $secondRow = array_combine($rows[0], $rows[2]);
    expect($firstRow['Gelombang'])->toBe('Gelombang 2')->and($firstRow['Periode'])->toBe("'=2027/2028 <script>bad()</script>")->and($firstRow['Lokasi Kampus'])->toBe('Kampus C');
    expect($secondRow['Gelombang'])->toBe('Gelombang 3')->and($secondRow['Periode'])->toBe('2028/2029')->and($secondRow['Lokasi Kampus'])->toBe('Kampus B');
    $html = $activity instanceof QuranExam
        ? view('quran-tests.report', ['rows' => $activity->assessments()->with('exam')->get(), 'scope' => $activity->title, 'filters' => [], 'exportedAt' => now(), 'letterhead' => 'data:image/png;base64,'])->render()
        : view('interviews.report', ['interview' => $activity, 'submissions' => $activity->submissions()->with('form')->get(), 'exportedAt' => now(), 'answerTable' => new InterviewAnswerTable((new Dompdf)->getFontMetrics()), 'letterhead' => 'data:image/png;base64,'])->render();
    expect($html)->toContain('Gelombang 2', 'Gelombang 3', 'Kampus C', 'Kampus B', '2028/2029', '&lt;script&gt;')->not->toContain('<script>bad()</script>', 'lulus MQ 2', '2020/2021');
    if ($activity instanceof QuranExam) {
        $activity->assessments()->where('registration_number', '000123')->update(['period' => '2027/2028']);
        $pdf = $this->get(route('quran-tests.pdf', ['exam_id' => $activity->id]))->assertOk();
        if ($path = getenv('SISURAT_RESPONDENT_QURAN_PDF')) {
            file_put_contents($path, $pdf->getContent());
        }
    } else {
        $activity->submissions()->where('registration_number', '000123')->update(['period' => '2027/2028']);
        $pdf = $this->get(route('interviews.pdf', $activity))->assertOk();
        if ($path = getenv('SISURAT_RESPONDENT_INTERVIEW_PDF')) {
            file_put_contents($path, $pdf->getContent());
        }
    }
    expect($pdf->getContent())->toStartWith('%PDF-');
})->with(['quran-exams', 'interviews']);
