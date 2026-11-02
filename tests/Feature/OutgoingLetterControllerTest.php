<?php

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use App\Models\ManualOutgoingLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 29));
});

function agendaUser(string $role = User::ROLE_ADMIN): User
{
    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

function agendaPayload(int $sequence = 455, int $year = 2026): array
{
    return ['recipient' => 'SMKN 1 Martapura', 'letter_date' => $year.'-10-29', 'subject' => 'Permohonan Izin Sosialisasi', 'letter_number' => sprintf('%03d/UNUHA.11/TU.2.1/X/%d', $sequence, $year), 'type' => 'permission'];
}

function agendaLetter(User $owner, int $number, string $type = 'permission', string $date = '2026-10-29', array $attributes = []): Letter
{
    $batch = LetterBatch::factory()->create(['user_id' => $owner->id, 'type' => $type, 'letter_date' => $date]);

    return Letter::factory()->create(['letter_batch_id' => $batch->id, 'sequence_number' => $number, 'letter_number' => sprintf('%03d/UNUHA.11/TU.2.1/X/%s', $number, substr($date, 0, 4)), 'institution_name' => $type === 'permission' ? 'Sekolah Contoh' : null, 'institution_type' => 'school', 'participant_name' => $type === 'pkkmb' ? 'Mahasiswa Contoh' : null, ...$attributes]);
}

function agendaCsv(string $content): array
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

test('agenda and its exports require an authenticated account', function () {
    foreach (['outgoing-letters.index', 'outgoing-letters.create', 'outgoing-letters.export', 'outgoing-letters.pdf'] as $name) {
        $this->get(route($name))->assertRedirect(route('login'));
    }
    $this->post(route('outgoing-letters.store'), agendaPayload())->assertRedirect(route('login'));
    $this->assertDatabaseCount('manual_outgoing_letters', 0);
});

test('agenda combines every letter type and manual records with their actual recipients and saved subjects', function () {
    $owner = agendaUser();
    $permission = agendaLetter($owner, 1);
    $permission->batch->update(['content_templates' => ['version' => 1, 'templates' => ['permission-school' => ['subject' => 'Perihal yang disesuaikan']]]]);
    $assignment = agendaLetter($owner, 2, 'assignment', attributes: ['recipient_district' => 'OKU Timur']);
    $assignment->assignees()->createMany([['name' => 'Dosen Pertama', 'office' => 'Dosen', 'district' => 'OKU Timur', 'sort_order' => 1], ['name' => 'Dosen Kedua', 'office' => 'Dosen', 'district' => 'OKU Timur', 'sort_order' => 2]]);
    agendaLetter($owner, 3, 'pkkmb');
    $general = agendaLetter($owner, 4, 'general', attributes: ['recipient_name' => 'Penerima Undangan']);
    $general->batch->update(['subject' => 'Undangan Rapat PMB']);
    ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'sequence_number' => 5, 'letter_number' => '005/MANUAL/X/2026', 'recipient' => 'Penerima Manual']);

    $this->actingAs($owner)->get(route('outgoing-letters.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('outgoing-letters/Index')->has('records.data', 5)
        ->where('records.data.0.subject', 'Perihal yang disesuaikan')->where('records.data.1.recipient', 'Dosen Pertama, Dosen Kedua - OKU Timur')->where('records.data.2.recipient', 'Mahasiswa Contoh')->where('records.data.3.subject', 'Undangan Rapat PMB')->where('records.data.4.source', 'manual')->where('stats.total', 5)->where('stats.automatic', 4)->where('stats.manual', 1));
});

test('agenda respects year period source type status and search filters', function (array $filters, array $numbers) {
    $owner = agendaUser();
    $first = agendaLetter($owner, 1, date: '2026-10-10');
    $first->batch->update(['content_templates' => ['version' => 1, 'templates' => ['permission-school' => ['subject' => 'Perihal khusus']]]]);
    ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'sequence_number' => 2, 'letter_number' => '002/MANUAL/X/2026', 'letter_date' => '2026-10-15', 'type' => 'assignment']);
    $trial = agendaLetter($owner, 3, date: '2026-10-20');
    $trial->batch->forceFill(['is_trial' => true])->save();
    agendaLetter($owner, 4, date: '2025-12-10');

    $response = $this->actingAs($owner)->get(route('outgoing-letters.index', $filters))->assertOk();
    $response->assertInertia(function (Assert $page) use ($numbers): void {
        $page->component('outgoing-letters/Index')->has('records.data', count($numbers));
        foreach ($numbers as $position => $number) {
            $page->where('records.data.'.$position.'.letter_number', $number);
        }
    });
})->with([
    'default official' => [[], ['001/UNUHA.11/TU.2.1/X/2026', '002/MANUAL/X/2026']],
    'manual source' => [['source' => 'manual'], ['002/MANUAL/X/2026']],
    'trial only' => [['status' => 'trial'], ['003/UNUHA.11/TU.2.1/X/2026']],
    'older year' => [['year' => 2025], ['004/UNUHA.11/TU.2.1/X/2025']],
    'date range' => [['from' => '2026-10-12', 'to' => '2026-10-16'], ['002/MANUAL/X/2026']],
    'end date only' => [['to' => '2026-10-12'], ['001/UNUHA.11/TU.2.1/X/2026']],
    'type' => [['type' => 'assignment'], ['002/MANUAL/X/2026']],
    'custom subject search' => [['q' => 'khusus'], ['001/UNUHA.11/TU.2.1/X/2026']],
    'all years' => [['year' => ''], ['004/UNUHA.11/TU.2.1/X/2025', '001/UNUHA.11/TU.2.1/X/2026', '002/MANUAL/X/2026']],
]);

test('deleted letters and deleted batches are excluded from agenda', function () {
    $owner = agendaUser();
    $letter = agendaLetter($owner, 1);
    $letter->delete();
    $other = agendaLetter($owner, 2);
    $other->batch->delete();
    $manual = ManualOutgoingLetter::factory()->create(['created_by' => $owner->id]);
    $manual->delete();

    $this->actingAs($owner)->get(route('outgoing-letters.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 0)->where('stats.total', 0));
});

test('recording a manual letter reserves its number for the ordinary letter creation flow', function () {
    $owner = agendaUser();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload())->assertSessionHasNoErrors()->assertRedirect(route('outgoing-letters.index', ['year' => 2026]));
    $this->assertDatabaseHas('manual_outgoing_letters', ['created_by' => $owner->id, 'sequence_number' => 455, 'letter_year' => 2026, 'recipient' => 'SMKN 1 Martapura']);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(456);
    $this->get(route('letters.permission.school'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('nextSequence', 456));
    $this->post(route('letters.store'), ['type' => 'permission', 'letter_date' => '2026-10-29', 'first_sequence' => 455, 'number_prefix' => 'UNUHA.11/TU.2.1', 'signatory_title' => 'Kepala Pusat PMB', 'signatory_name' => 'Petugas', 'items' => [['institution_type' => 'school', 'institution_name' => 'SMA Contoh', 'recipient_position' => 'Kepala']]])->assertSessionHasErrors('first_sequence');
    $this->assertDatabaseCount('letters', 0);
});

test('historical manual records can fill unused earlier numbers without reducing the next number', function () {
    $owner = agendaUser();
    LetterNumbering::query()->create(['year' => 2026, 'next_sequence' => 500]);
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload(5))->assertSessionHasNoErrors();
    expect(LetterNumbering::query()->where('year', 2026)->value('next_sequence'))->toBe(500);
});

test('manual numbers cannot collide with official letters or reserved deleted numbers', function (bool $deleted) {
    $owner = agendaUser();
    $letter = agendaLetter($owner, 455);
    if ($deleted) {
        $letter->delete();
        $letter->batch->delete();
    }
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload())->assertSessionHasErrors('letter_number');
    $this->assertDatabaseCount('manual_outgoing_letters', 0);
})->with([false, true]);

test('trial letter numbers do not block a genuine manual record', function () {
    $owner = agendaUser();
    $letter = agendaLetter($owner, 455);
    $letter->batch->forceFill(['is_trial' => true])->save();
    $letter->forceFill(['numbering_scope' => 'trial-'.$letter->batch->id])->save();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload())->assertSessionHasNoErrors();
    $this->assertDatabaseCount('manual_outgoing_letters', 1);
});

test('manual records validate complete numbers dates identity and year consistency', function (array $changes, string $field) {
    $owner = agendaUser();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), [...agendaPayload(), ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('manual_outgoing_letters', 0);
})->with([
    'missing receiver' => [['recipient' => '  '], 'recipient'], 'missing subject' => [['subject' => ''], 'subject'],
    'invalid number' => [['letter_number' => 'ABC'], 'letter_number'], 'zero sequence' => [['letter_number' => '000/CODE/X/2026'], 'letter_number'],
    'wrong year' => [['letter_number' => '455/CODE/X/2025'], 'letter_number'], 'invalid date' => [['letter_date' => '2026-02-30'], 'letter_date'],
    'unsupported type' => [['type' => 'invalid'], 'type'],
]);

test('correcting a manual number retains the old reservation and prevents stale overwrites', function () {
    $owner = agendaUser();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload(455))->assertSessionHasNoErrors();
    $original = ManualOutgoingLetter::query()->sole();
    $this->put(route('outgoing-letters.update', $original), [...agendaPayload(456), 'expected_version' => 1])->assertSessionHasNoErrors();
    $this->assertSoftDeleted($original);
    $replacement = ManualOutgoingLetter::query()->sole();
    expect($replacement->sequence_number)->toBe(456);
    expect(Letter::lastOfficialSequenceForYear(2026))->toBe(456);
    $this->post(route('outgoing-letters.store'), agendaPayload(455))->assertSessionHasErrors('letter_number');
    $this->put(route('outgoing-letters.update', $replacement), [...agendaPayload(456), 'recipient' => 'Koreksi penerima', 'expected_version' => $replacement->version])->assertSessionHasNoErrors();
    $this->put(route('outgoing-letters.update', $replacement), [...agendaPayload(456), 'recipient' => 'Data lama', 'expected_version' => $replacement->version])->assertSessionHasErrors('expected_version');
    expect($replacement->fresh()->recipient)->toBe('Koreksi penerima');
});

test('deleting a manual record preserves its reservation and protects numbering settings', function () {
    $owner = agendaUser();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload())->assertSessionHasNoErrors();
    $entry = ManualOutgoingLetter::query()->sole();
    $this->delete(route('outgoing-letters.destroy', $entry))->assertRedirect();
    $this->assertSoftDeleted($entry);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(456);
    $this->post(route('outgoing-letters.store'), agendaPayload())->assertSessionHasErrors('letter_number');
    $this->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 1, 'expected_next_sequence' => 456])->assertSessionHasErrors('next_sequence');
});

test('staff cannot modify other users manual entries while the administrator can', function () {
    $owner = agendaUser(User::ROLE_STAFF);
    $other = agendaUser(User::ROLE_STAFF);
    $entry = ManualOutgoingLetter::factory()->create(['created_by' => $owner->id]);
    $this->actingAs($other)->get(route('outgoing-letters.edit', $entry))->assertForbidden();
    $this->put(route('outgoing-letters.update', $entry), [...agendaPayload(), 'expected_version' => 1])->assertForbidden();
    $this->delete(route('outgoing-letters.destroy', $entry))->assertForbidden();
    $this->actingAs(agendaUser())->get(route('outgoing-letters.edit', $entry))->assertOk();
});

test('CSV contains all matching records beyond pagination and escapes spreadsheet formulas', function () {
    $owner = agendaUser();
    for ($number = 1; $number <= 257; $number++) {
        ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'sequence_number' => $number, 'letter_number' => sprintf('%03d/CODE/X/2026', $number), 'recipient' => $number === 1 ? '=HYPERLINK("test")' : 'Penerima '.$number]);
    }
    $this->actingAs($owner)->get(route('outgoing-letters.index', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.from', 26)->has('records.data', 25));
    $response = $this->get(route('outgoing-letters.export', ['page' => 2]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $rows = agendaCsv($response->streamedContent());
    expect($rows)->toHaveCount(258);
    expect($rows[0])->toBe(['No.', 'Alamat/Penerima', 'Tanggal', 'Perihal', 'Nomor Surat']);
    expect($rows[1][1])->toBe("'=HYPERLINK(\"test\")");
    expect($rows[257][4])->toBe('257/CODE/X/2026');
});

test('PDF produces a full multipage agenda using the reference columns and safe text', function () {
    $owner = agendaUser();
    for ($number = 1; $number <= 65; $number++) {
        ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'sequence_number' => $number, 'letter_number' => sprintf('%03d/UNUHA.11/TU.2.1/X/2026', $number), 'recipient' => $number === 10 ? str_repeat('Penerima panjang ', 20) : 'Sekolah Contoh '.$number, 'subject' => $number === 10 ? str_repeat('Perihal panjang ', 20) : 'Permohonan Izin Sosialisasi dan Promosi']);
    }
    $response = $this->actingAs($owner)->get(route('outgoing-letters.pdf', ['page' => 2]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    File::ensureDirectoryExists(storage_path('framework/testing'));
    File::put(storage_path('framework/testing/outgoing-agenda-preview.pdf'), $response->getContent());
    $html = view('outgoing-letters.report', ['filters' => ['year' => 2026, 'from' => '', 'to' => '', 'source' => '', 'status' => 'official', 'type' => '', 'q' => ''], 'rows' => [['recipient' => '<script>test</script>', 'letter_date' => '2026-10-29', 'subject' => '<b>contoh</b>', 'letter_number' => '001/CODE/X/2026', 'is_trial' => false]]])->render();
    expect($html)->not->toContain('<script>test</script>')->toContain('&lt;script&gt;test&lt;/script&gt;');
});

test('manual corrections across years retain both numbering reservations', function () {
    $owner = agendaUser();
    $this->actingAs($owner)->post(route('outgoing-letters.store'), agendaPayload(5))->assertSessionHasNoErrors();
    $original = ManualOutgoingLetter::query()->sole();
    $this->put(route('outgoing-letters.update', $original), [...agendaPayload(1, 2027), 'expected_version' => 1])->assertSessionHasNoErrors()->assertRedirect(route('outgoing-letters.index', ['year' => 2027]));
    expect(Letter::nextSequencesByYear()['2026'])->toBe(6);
    expect(Letter::nextSequencesByYear()['2027'])->toBe(2);
    $this->assertSoftDeleted($original);
    $this->post(route('outgoing-letters.store'), agendaPayload(5))->assertSessionHasErrors('letter_number');
});

test('invalid filters return validation errors instead of changing the export scope', function (array $filters, string $field) {
    $this->actingAs(agendaUser())->get(route('outgoing-letters.export', $filters))->assertSessionHasErrors($field);
})->with([
    'backwards date range' => [['from' => '2026-10-29', 'to' => '2026-10-01'], 'to'],
    'invalid status' => [['status' => 'secret'], 'status'],
    'invalid source' => [['source' => 'unknown'], 'source'],
]);

test('manual entry permissions allow its owner and administrator while excluding unrelated or inactive accounts', function () {
    $owner = agendaUser(User::ROLE_STAFF);
    $other = agendaUser(User::ROLE_STAFF);
    $administrator = agendaUser();
    $inactive = User::factory()->create(['is_active' => false]);
    $entry = ManualOutgoingLetter::factory()->create(['created_by' => $owner->id]);
    expect(Gate::forUser($owner)->allows('create', ManualOutgoingLetter::class))->toBeTrue();
    expect(Gate::forUser($owner)->allows('update', $entry))->toBeTrue();
    expect(Gate::forUser($administrator)->allows('delete', $entry))->toBeTrue();
    expect(Gate::forUser($other)->allows('update', $entry))->toBeFalse();
    expect(Gate::forUser($inactive)->allows('create', ManualOutgoingLetter::class))->toBeFalse();
    expect(Gate::forUser($inactive)->allows('delete', $entry))->toBeFalse();
});
