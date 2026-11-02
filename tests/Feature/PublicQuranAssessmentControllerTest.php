<?php

use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\StudyProgram;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

function publicMqPayload(QuranExam $exam, array $overrides = []): array
{
    $program = StudyProgram::query()->firstOrCreate(['identity_key' => StudyProgram::identityKey('Informatika')], ['name' => 'Informatika']);

    return [...[
        'wave' => 1, 'period' => '2027/2028', 'academic_year' => '2027/2028', 'campus' => 'B',
        'name' => 'Peserta Contoh', 'registration_number' => '0001456', 'study_program_id' => $program->id,
        'examiner_name' => 'Penguji Contoh', 'reading_reference' => 'Al-Fatihah 1-7',
        'fasohah_score' => 70, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70,
        'notes' => 'Catatan penguji', 'submission_token' => (string) Str::uuid(), 'exam_version' => $exam->version,
    ], ...$overrides];
}

test('an invited examiner opens the form as a guest without private assessment data', function () {
    $exam = QuranExam::factory()->create();
    $this->get(route('quran-exams.join', $exam->public_token))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('quran-exams/Join')->where('exam.is_open', true)
            ->where('auth.user', null)->missing('exam.public_token')->missing('exam.created_by')->missing('assessments')->where('receipt', null));
});

test('unknown examiner links return 404 instead of a login page', function () {
    $this->get(route('quran-exams.join', str_repeat('z', 64)))->assertNotFound();
});

test('a guest saves a complete assessment with server owned scope date and MQ category', function () {
    $exam = QuranExam::factory()->create(['assessment_date' => '2026-10-29']);
    $other = QuranExam::factory()->create();
    $data = publicMqPayload($exam, ['fasohah_score' => 69, 'result' => 'mq2', 'created_by' => $other->created_by, 'quran_exam_id' => $other->id, 'assessment_date' => '2000-01-01']);
    $this->post(route('quran-exams.submit', $exam->public_token), $data)->assertSessionHasNoErrors()->assertRedirect(route('quran-exams.join', $exam->public_token));
    $saved = QuranAssessment::query()->sole();
    expect($saved->quran_exam_id)->toBe($exam->id)->and($saved->created_by)->toBe($exam->created_by)
        ->and($saved->assessment_date->format('Y-m-d'))->toBe('2026-10-29')->and($saved->result)->toBe('mq1')
        ->and($saved->averageScore())->toBe(69.75)->and($saved->registration_number)->toBe('0001456');
    $this->get(route('quran-exams.join', $exam->public_token))->assertInertia(fn (Assert $page) => $page->where('receipt.result', 'mq1')->where('receipt.name', 'Peserta Contoh'));
});

test('every public criterion is required and limited to integer scores from 1 to 100', function (string $field, mixed $value) {
    $exam = QuranExam::factory()->create();
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, [$field => $value]))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('quran_assessments', 0);
})->with(['fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score'])->with([null, 0, 101, 69.5]);

test('guest submissions require participant examiner reading and course details', function (string $field) {
    $exam = QuranExam::factory()->create();
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, [$field => null]))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('quran_assessments', 0);
})->with(['name', 'registration_number', 'examiner_name', 'reading_reference', 'study_program_id', 'submission_token', 'exam_version']);

test('a course removed before submission is rejected without losing the whole request', function () {
    $exam = QuranExam::factory()->create();
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, ['study_program_id' => 999999]))->assertSessionHasErrors('study_program_id');
    $this->assertDatabaseCount('quran_assessments', 0);
});

test('closed and inactive owner forms do not accept grades', function (bool $inactive) {
    $exam = QuranExam::factory()->create(['is_open' => $inactive]);
    if ($inactive) {
        $exam->creator->forceFill(['is_active' => false])->save();
    }
    $this->get(route('quran-exams.join', $exam->public_token))->assertInertia(fn (Assert $page) => $page->where('exam.is_open', false)->has('studyPrograms', 0));
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam))->assertSessionHasErrors('exam');
    $this->assertDatabaseCount('quran_assessments', 0);
})->with(['closed activity' => false, 'inactive account' => true]);

test('a repeated submission token is idempotent and never overwrites the original score', function () {
    $exam = QuranExam::factory()->create();
    $data = publicMqPayload($exam);
    $this->post(route('quran-exams.submit', $exam->public_token), $data)->assertSessionHasNoErrors();
    $exam->update(['is_open' => false]);
    $this->post(route('quran-exams.submit', $exam->public_token), [...$data, 'fasohah_score' => 1])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('quran_assessments', 1);
    expect(QuranAssessment::query()->sole()->fasohah_score)->toBe(70);
});

test('one registration can be assessed once per activity while a later retest is allowed', function () {
    $exam = QuranExam::factory()->create();
    $next = QuranExam::factory()->create(['created_by' => $exam->created_by]);
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, ['registration_number' => 'PMB-a01']))->assertSessionHasNoErrors();
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, ['registration_number' => ' pmb-A01 ']))->assertSessionHasErrors('registration_number');
    $this->post(route('quran-exams.submit', $next->public_token), publicMqPayload($next, ['registration_number' => 'PMB-a01']))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('quran_assessments', 2);
});

test('a token from another activity cannot collide with an existing submission', function () {
    $exam = QuranExam::factory()->create();
    $next = QuranExam::factory()->create(['created_by' => $exam->created_by]);
    $data = publicMqPayload($exam);
    $this->post(route('quran-exams.submit', $exam->public_token), $data)->assertSessionHasNoErrors();
    $this->post(route('quran-exams.submit', $next->public_token), publicMqPayload($next, ['submission_token' => $data['submission_token']]))->assertSessionHasErrors('submission_token');
    $this->assertDatabaseCount('quran_assessments', 1);
});

test('a stale public form cannot silently use changed activity information', function () {
    $exam = QuranExam::factory()->create();
    $data = publicMqPayload($exam);
    $exam->update(['version' => (string) Str::uuid()]);
    $this->post(route('quran-exams.submit', $exam->public_token), $data)->assertSessionHasErrors('exam_version');
    $this->assertDatabaseCount('quran_assessments', 0);
});

test('a receipt belongs only to the current examiner session', function () {
    $exam = QuranExam::factory()->create();
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam))->assertSessionHasNoErrors();
    $this->flushSession();
    $this->get(route('quran-exams.join', $exam->public_token))->assertInertia(fn (Assert $page) => $page->where('receipt', null)->missing('assessments'));
});

test('guest score submissions are rate limited', function () {
    $this->withCookie(config('session.cookie'), Str::random(40));
    $exam = QuranExam::factory()->create();
    for ($i = 1; $i <= 20; $i++) {
        $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, ['registration_number' => 'RATE-'.$i]))->assertSessionHasNoErrors()->assertRedirect();
    }
    $this->post(route('quran-exams.submit', $exam->public_token), publicMqPayload($exam, ['registration_number' => 'RATE-21']))->assertStatus(429);
    $this->assertDatabaseCount('quran_assessments', 20);
});
