<?php

use App\Models\QuranAssessment;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function mqUser(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
}

function mqPayload(array $overrides = []): array
{
    $program = StudyProgram::query()->firstOrCreate(['identity_key' => StudyProgram::identityKey('Informatika')], ['name' => 'Informatika']);

    return [...[
        'name' => 'Peserta Contoh', 'registration_number' => '001234',
        'study_program_id' => $program->id, 'retain_study_program' => false,
        'assessment_date' => '2026-10-29', 'examiner_name' => 'Penguji Contoh',
        'reading_reference' => 'Al-Fatihah 1–7',
        'fasohah_score' => 70, 'tajwid_score' => 70, 'makhraj_score' => 70, 'fluency_score' => 70,
        'notes' => 'Catatan penguji', 'submission_token' => (string) Str::uuid(),
    ], ...$overrides];
}

function mqRecord(User $owner, array $overrides = []): QuranAssessment
{
    $data = mqPayload();
    unset($data['study_program_id'], $data['retain_study_program']);

    return QuranAssessment::query()->create([
        ...$data, 'study_program' => 'Informatika', 'result' => QuranAssessment::RESULT_MQ2,
        'created_by' => $owner->id, 'version' => (string) Str::uuid(), ...$overrides,
    ]);
}

test('MQ category follows the average of four equally weighted scores', function (array $scores, string $result, float $average) {
    $this->actingAs(mqUser())->post(route('quran-tests.store'), mqPayload(array_combine(
        ['fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score'], $scores,
    )))->assertSessionHasNoErrors()->assertRedirect();

    $saved = QuranAssessment::query()->sole();
    expect($saved->result)->toBe($result)
        ->and($saved->averageScore())->toEqual($average)
        ->and($saved->totalScore())->toBe(array_sum($scores))
        ->and($saved->registration_number)->toBe('001234');
})->with([
    'exact threshold' => [[70, 70, 70, 70], 'mq2', 70.0],
    'just below threshold' => [[69, 70, 70, 70], 'mq1', 69.75],
    'low criterion may be balanced by others' => [[1, 100, 100, 100], 'mq2', 75.25],
    'mixed scores exactly 70' => [[69, 70, 70, 71], 'mq2', 70.0],
    'lowest valid scores' => [[1, 1, 1, 1], 'mq1', 1.0],
    'highest valid scores' => [[100, 100, 100, 100], 'mq2', 100.0],
]);

test('every criterion rejects zero out of range fractional and nonnumeric values', function (string $field, mixed $value) {
    $this->actingAs(mqUser())->post(route('quran-tests.store'), mqPayload([$field => $value]))
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('quran_assessments', 0);
})->with(['fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score'])
    ->with([0, -1, 101, 69.5, 'abc']);

test('incomplete assessments remain pending instead of receiving a category', function (string $missing) {
    $data = mqPayload([$missing => null, 'examiner_name' => null, 'reading_reference' => null, 'result' => 'mq2']);
    $this->actingAs(mqUser())->post(route('quran-tests.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $saved = QuranAssessment::query()->sole();
    expect($saved->result)->toBe('pending')->and($saved->averageScore())->toBeNull()->and($saved->totalScore())->toBeNull();
})->with(['fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score']);

test('a draft can be recorded before the face to face test', function () {
    $this->actingAs(mqUser())->post(route('quran-tests.store'), mqPayload([
        'fasohah_score' => null, 'tajwid_score' => null, 'makhraj_score' => null, 'fluency_score' => null,
        'examiner_name' => null, 'reading_reference' => null,
    ]))->assertSessionHasNoErrors()->assertRedirect();
    expect(QuranAssessment::query()->sole()->result)->toBe('pending');
});

test('complete scores require examiner and reading reference regardless of a forged pending result', function () {
    $this->actingAs(mqUser())->post(route('quran-tests.store'), mqPayload([
        'examiner_name' => null, 'reading_reference' => null, 'result' => 'pending',
    ]))->assertSessionHasErrors(['examiner_name', 'reading_reference']);
    $this->assertDatabaseCount('quran_assessments', 0);
});

test('clients cannot override the calculated category', function () {
    $this->actingAs(mqUser())->post(route('quran-tests.store'), mqPayload([
        'fasohah_score' => '69', 'tajwid_score' => '70', 'makhraj_score' => '70', 'fluency_score' => '70', 'result' => 'mq2',
    ]))->assertSessionHasNoErrors()->assertRedirect();
    expect(QuranAssessment::query()->sole()->result)->toBe('mq1');
});

test('changing a score recalculates MQ and rejects a stale subsequent update', function () {
    $owner = mqUser();
    $saved = mqRecord($owner);
    $originalVersion = $saved->version;
    $data = mqPayload(['fasohah_score' => 69, 'retain_study_program' => true, 'version' => $originalVersion]);
    unset($data['submission_token']);
    $this->actingAs($owner)->put(route('quran-tests.update', $saved), $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($saved->refresh()->result)->toBe('mq1')->and($saved->averageScore())->toBe(69.75);
    $this->put(route('quran-tests.update', $saved), [...$data, 'fasohah_score' => 100])->assertSessionHasErrors('version');
    expect($saved->refresh()->fasohah_score)->toBe(69)->and($saved->result)->toBe('mq1');
});

test('legacy results remain intact and acquire no invented Fasohah score or MQ category', function () {
    $owner = mqUser();
    $old = mqRecord($owner, ['fasohah_score' => null, 'result' => 'fluent', 'makhraj_score' => 80]);
    $this->actingAs($owner)->get(route('quran-tests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('stats.legacy', 1)->where('stats.mq2', 0)->where('stats.mq1', 0)
        ->where('assessments.data.0.average_score', null));
    $this->get(route('quran-tests.print', $old))->assertOk()->assertSee('Penilaian lama: Lancar')->assertSee('Belum diisi');
    $this->assertDatabaseHas('quran_assessments', ['id' => $old->id, 'fasohah_score' => null, 'makhraj_score' => 80, 'result' => 'fluent']);
});

test('MQ filters and summary counts exclude records belonging to another account', function () {
    $owner = mqUser();
    mqRecord($owner);
    mqRecord($owner, ['fasohah_score' => 69, 'result' => 'mq1']);
    mqRecord(mqUser());
    $this->actingAs($owner)->get(route('quran-tests.index', ['result' => 'mq1']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('stats.total', 2)->where('stats.mq1', 1)->where('stats.mq2', 1)
        ->has('assessments.data', 1)->where('assessments.data.0.result', 'mq1')
        ->where('assessments.data.0.average_score', 69.75));
});

test('the print report contains all four scores total average and classification', function () {
    $owner = mqUser();
    $saved = mqRecord($owner);
    $this->actingAs($owner)->get(route('quran-tests.print', $saved))->assertOk()
        ->assertSeeInOrder(['Fasohah', 'Tajwid', 'Makhroj', 'Kelancaran', 'Total (maksimal 400)', '280', 'Rata-rata', '70,00', 'MQ 2'])
        ->assertSee('Nilai (1–100)')->assertDontSee('Kategori hasil ditetapkan oleh penguji.');
});

test('CSV exports all matching assessments beyond the page and chunk limits with all four criteria', function () {
    $owner = mqUser();
    $template = mqRecord($owner)->getAttributes();
    unset($template['id']);
    $rows = [];
    foreach (range(2, 257) as $number) {
        $rows[] = [...$template, 'name' => 'Peserta '.$number, 'submission_token' => (string) Str::uuid()];
    }
    QuranAssessment::query()->insert($rows);
    mqRecord(mqUser(), ['name' => 'Peserta akun lain']);
    $response = $this->actingAs($owner)->get(route('quran-tests.export', ['result' => 'mq2']));
    $response->assertOk();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, substr($response->streamedContent(), 3));
    rewind($stream);
    $csv = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $csv[] = $row;
    }
    fclose($stream);
    expect($csv)->toHaveCount(258);
    $record = array_combine($csv[0], $csv[1]);
    expect($record['Fasohah'])->toBe('70')->and($record['Tajwid'])->toBe('70')
        ->and($record['Makhroj'])->toBe('70')->and($record['Kelancaran'])->toBe('70')
        ->and($record['Total (400)'])->toBe('280')->and($record['Rata-rata (100)'])->toBe('70')
        ->and($record['Kategori MQ / Status'])->toBe('Berhak mengikuti MQ 2');
});

test('Quran assessment pages exports and mutations remain protected', function () {
    $owner = mqUser();
    $saved = mqRecord($owner);
    foreach (['quran-tests.index', 'quran-tests.create', 'quran-tests.export'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
    $this->get(route('quran-tests.print', $saved))->assertRedirect(route('login'));
    $this->actingAs(mqUser());
    foreach (['quran-tests.show', 'quran-tests.edit', 'quran-tests.print'] as $route) {
        $this->get(route($route, $saved))->assertForbidden();
    }
    $data = mqPayload(['version' => $saved->version]);
    unset($data['submission_token']);
    $this->put(route('quran-tests.update', $saved), $data)->assertForbidden();
    $this->delete(route('quran-tests.destroy', $saved))->assertForbidden();
    expect($saved->fresh()->result)->toBe('mq2');
});
