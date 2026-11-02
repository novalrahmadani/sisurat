<?php

use App\Models\QuranAssessment;
use App\Models\QuranExam;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

function examOwner(): User
{
    return User::factory()->create(['is_active' => true]);
}
function examSettings(array $extra = []): array
{
    return [...['title' => 'Tes MQ Gelombang 1', 'description' => 'Petunjuk tes', 'assessment_date' => '2026-10-29', 'is_open' => true], ...$extra];
}

test('exam administration and QR downloads require login', function () {
    $exam = QuranExam::factory()->create();
    foreach (['quran-exams.index', 'quran-exams.create'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
    foreach (['quran-exams.show', 'quran-exams.qr'] as $route) {
        $this->get(route($route, $exam))->assertRedirect(route('login'));
    }
    $this->post(route('quran-exams.store'), examSettings())->assertRedirect(route('login'));
});

test('an owner creates a separate examiner link without choosing another owner or token', function () {
    $owner = examOwner();
    $this->actingAs($owner)->post(route('quran-exams.store'), examSettings(['created_by' => 999999, 'public_token' => str_repeat('a', 64)]))->assertSessionHasNoErrors()->assertRedirect();
    $exam = QuranExam::query()->sole();
    expect($exam->created_by)->toBe($owner->id)->and(strlen($exam->public_token))->toBe(64)->and($exam->public_token)->not->toBe(str_repeat('a', 64));
    $this->get(route('quran-exams.show', $exam))->assertInertia(fn (Assert $page) => $page->component('quran-exams/Show')->where('exam.assessments_count', 0)->has('publicUrl')->missing('exam.public_token'));
});

test('activity settings validate required details', function () {
    $this->actingAs(examOwner())->post(route('quran-exams.store'), examSettings(['title' => '', 'assessment_date' => 'not-a-date']))->assertSessionHasErrors(['title', 'assessment_date']);
    $this->assertDatabaseCount('quran_exams', 0);
});

test('an owner can close an activity but stale settings cannot reopen it', function () {
    $owner = examOwner();
    $exam = QuranExam::factory()->create(['created_by' => $owner->id, 'assessment_date' => '2026-10-29']);
    $data = examSettings(['version' => $exam->version, 'is_open' => false]);
    $this->actingAs($owner)->put(route('quran-exams.update', $exam), $data)->assertSessionHasNoErrors();
    expect($exam->refresh()->is_open)->toBeFalse();
    $this->put(route('quran-exams.update', $exam), [...$data, 'is_open' => true])->assertSessionHasErrors('version');
    expect($exam->refresh()->is_open)->toBeFalse();
});

test('another account cannot read change or download QR for an owners activity', function () {
    $exam = QuranExam::factory()->create();
    $this->actingAs(examOwner());
    foreach (['quran-exams.show', 'quran-exams.qr'] as $route) {
        $this->get(route($route, $exam))->assertForbidden();
    }
    $this->put(route('quran-exams.update', $exam), examSettings(['version' => $exam->version]))->assertForbidden();
    $this->get(route('quran-exams.index'))->assertInertia(fn (Assert $page) => $page->has('exams.data', 0));
});

test('the owners QR is a downloadable locally generated PNG', function () {
    $exam = QuranExam::factory()->create();
    $response = $this->actingAs($exam->creator)->get(route('quran-exams.qr', ['quran_exam' => $exam, 'download' => 1]));
    $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertDownload('qr-penguji-mq-'.$exam->id.'.png');
    expect($response->getContent())->toStartWith("\x89PNG");
});

test('the date cannot change after scores have been recorded but closing remains allowed', function () {
    $exam = QuranExam::factory()->create(['assessment_date' => '2026-10-29']);
    $exam->assessments()->create(['name' => 'Peserta', 'registration_number' => '0001', 'study_program' => 'Informatika', 'assessment_date' => '2026-10-29', 'created_by' => $exam->created_by, 'version' => (string) Str::uuid(), 'submission_token' => (string) Str::uuid()]);
    $this->actingAs($exam->creator)->put(route('quran-exams.update', $exam), examSettings(['assessment_date' => '2026-10-30', 'version' => $exam->version]))->assertSessionHasErrors('assessment_date');
    $this->put(route('quran-exams.update', $exam), examSettings(['is_open' => false, 'version' => $exam->version]))->assertSessionHasNoErrors();
    expect($exam->refresh()->assessment_date->format('Y-m-d'))->toBe('2026-10-29')->and($exam->is_open)->toBeFalse();
});

test('admin corrections cannot duplicate a registration in the same activity', function () {
    $exam = QuranExam::factory()->create(['assessment_date' => '2026-10-29']);
    $rows = [];
    foreach (['0001', '0002'] as $registration) {
        $rows[] = $exam->assessments()->create([
            'name' => 'Peserta '.$registration, 'registration_number' => $registration,
            'registration_key' => QuranAssessment::registrationKey($registration), 'study_program' => 'Informatika',
            'assessment_date' => '2026-10-29', 'created_by' => $exam->created_by,
            'version' => (string) Str::uuid(), 'submission_token' => (string) Str::uuid(),
        ]);
    }
    $data = ['name' => 'Peserta Koreksi', 'registration_number' => '0002', 'retain_study_program' => true,
        'assessment_date' => '2026-10-29', 'examiner_name' => 'Penguji', 'reading_reference' => 'Al-Fatihah 1-7',
        'fasohah_score' => 69, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70,
        'version' => $rows[0]->version];
    $this->actingAs($exam->creator)->put(route('quran-tests.update', $rows[0]), $data)->assertSessionHasErrors('registration_number');
    expect($rows[0]->refresh()->registration_number)->toBe('0001');
    $this->put(route('quran-tests.update', $rows[0]), [...$data, 'registration_number' => '0003'])->assertSessionHasNoErrors();
    expect($rows[0]->refresh()->registration_number)->toBe('0003')->and($rows[0]->result)->toBe('mq1');
    $this->assertDatabaseCount('quran_assessments', 2);
});
