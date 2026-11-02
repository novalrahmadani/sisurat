<?php

use App\Models\InternshipAttendance;
use App\Models\InternshipParticipant;
use App\Models\InternshipSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 08:15:00', 'Asia/Jakarta'));
});

function internshipInk(): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    $pixels = '';
    for ($y = 0; $y < 32; $y++) {
        $pixels .= "\0";
        for ($x = 0; $x < 64; $x++) {
            $pixels .= abs($x - $y * 2) < 4 ? "\x10\x10\x10" : "\xff\xff\xff";
        }
    }

    return 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 64, 32, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($pixels)).$chunk('IEND', ''));
}

function internshipAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
}

function internshipPayload(): array
{
    return ['name' => 'Peserta Uji', 'participant_category' => 'university', 'student_number' => '000123', 'institution' => 'Kampus Contoh', 'study_program' => 'Informatika',
        'start_date' => '2026-10-01', 'end_date' => '2026-11-13', 'is_active' => true];
}

function internshipSession(InternshipParticipant $participant): array
{
    InternshipSetting::query()->firstOrCreate(['id' => 1], ['latitude' => -4.123, 'longitude' => 104.123, 'radius_meters' => 100]);

    return ['internship_access' => ['id' => $participant->id, 'fingerprint' => $participant->access_code_hash, 'expires_at' => now()->addHours(12)->timestamp]];
}

function internshipPosition(): array
{
    return ['latitude' => -4.123, 'longitude' => 104.123, 'accuracy' => 10, 'position_captured_at' => now()->timestamp];
}

test('homepage and internship form are available to visitors without exposing a participant directory', function () {
    InternshipParticipant::factory()->create(['name' => 'Nama Privat']);
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('auth.user', null));
    $this->get(route('internship.show'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('internship/Public')->where('participant', null)->where('attendance', null)->missing('participants'));
});

test('admin registers a participant and receives a one use device access link while only its hash is stored', function () {
    $this->actingAs(internshipAdmin())->post(route('internships.store'), internshipPayload())->assertSessionHasNoErrors()->assertRedirect(route('internships.index', ['tab' => 'participants']));
    $url = session('internship_issued_access.url');
    $token = basename(parse_url($url, PHP_URL_PATH));
    $participant = InternshipParticipant::firstOrFail();
    expect($participant->recovery_token_hash)->toBe(hash('sha256', $token));
    expect($participant->access_code_hash)->toBeNull();
    $this->get(route('internships.index'))->assertInertia(fn (Assert $page) => $page->where('issuedAccess.url', $url)->missing('participants.data.0.recovery_token_hash')->missing('participants.data.0.device_token_hash'));
    $this->get(route('internships.index'))->assertInertia(fn (Assert $page) => $page->where('issuedAccess', null));
});

test('participant registration requires identity and valid inclusive dates', function () {
    $this->actingAs(internshipAdmin())->post(route('internships.store'), [])->assertSessionHasErrors(['name', 'student_number', 'institution', 'start_date', 'end_date', 'is_active']);
    $this->post(route('internships.store'), [...internshipPayload(), 'end_date' => '2026-09-30'])->assertSessionHasErrors(['end_date' => 'Tanggal selesai harus sama atau setelah tanggal mulai.']);
    $this->assertDatabaseCount('internship_participants', 0);
});

test('duplicate student identifiers are refused and editing the same participant keeps the access code', function () {
    $participant = InternshipParticipant::factory()->create(internshipPayload());
    $oldHash = $participant->access_code_hash;
    $this->actingAs(internshipAdmin())->post(route('internships.store'), internshipPayload())->assertSessionHasErrors('student_number');
    $this->put(route('internships.update', $participant), [...internshipPayload(), 'name' => 'Nama Revisi'])->assertSessionHasNoErrors();
    expect($participant->fresh()->name)->toBe('Nama Revisi');
    expect($participant->fresh()->access_code_hash)->toBe($oldHash);
});

test('guests cannot access internship administration', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['internships.index', 'internships.export', 'internships.print']);

test('staff cannot read or change internship administration', function () {
    $staff = User::factory()->create(['is_active' => true, 'role' => User::ROLE_STAFF]);
    $participant = InternshipParticipant::factory()->create();
    $this->actingAs($staff)->get(route('internships.index'))->assertForbidden();
    $this->get(route('internships.export'))->assertForbidden();
    $this->get(route('internships.print'))->assertForbidden();
    $this->post(route('internships.store'), internshipPayload())->assertForbidden();
    $this->put(route('internships.update', $participant), internshipPayload())->assertForbidden();
    $this->post(route('internships.recover-access', $participant))->assertForbidden();
});

test('wrong codes do not open participant data', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->post(route('internship.verify'), ['student_number' => $participant->student_number, 'access_code' => 'SALAH'])->assertSessionHasErrors('access_code');
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant', null));
});

test('malformed identity input is rejected before checking an access code', function () {
    $this->post(route('internship.verify'), ['student_number' => ['invalid'], 'access_code' => 'SALAH'])->assertSessionHasErrors('student_number');
});

test('a valid code opens only that participants identity and no admin account', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->post(route('internship.verify'), ['student_number' => $participant->student_number, 'access_code' => ' kodemagang12 '])->assertSessionHasNoErrors()->assertRedirect(route('internship.show'));
    $this->assertGuest();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant.name', $participant->name)->where('participant.can_attend', true)->missing('participant.access_code_hash')->missing('participants'));
});

test('check in records server time in WIB and ignores submitted participant IDs and timestamps', function () {
    $participant = InternshipParticipant::factory()->create();
    $other = InternshipParticipant::factory()->create();
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk(), 'note' => 'Tugas hari ini', 'internship_participant_id' => $other->id, 'checked_in_at' => '2000-01-01 01:00:00'])
        ->assertSessionHasNoErrors()->assertRedirect(route('internship.show'));
    $this->assertDatabaseHas('internship_attendances', ['internship_participant_id' => $participant->id, 'checked_in_at' => '2026-10-29 01:15:00']);
    expect(InternshipAttendance::first()->attendance_date->toDateString())->toBe('2026-10-29');
    $this->assertDatabaseCount('internship_attendances', 1);
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('attendance.checked_in_at', '08:15:00')->where('attendance.checked_out_at', null)->missing('attendance.check_in_signature'));
});

test('duplicate check in does not overwrite the original attendance', function () {
    $participant = InternshipParticipant::factory()->create();
    $payload = [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()];
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), $payload)->assertSessionHasNoErrors();
    $this->travel(1)->hours();
    $this->post(route('internship.submit'), [...$payload, ...internshipPosition()])->assertSessionHasErrors('action');
    $this->assertDatabaseCount('internship_attendances', 1);
    expect(InternshipAttendance::first()->checked_in_at->format('H:i:s'))->toBe('01:15:00');
});

test('checkout requires an existing check in on the current day', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_out', 'signature' => internshipInk()])->assertSessionHasErrors(['action' => 'Catat absensi masuk terlebih dahulu sebelum absen pulang.']);
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('checkout completes todays attendance and cannot be overwritten by a duplicate request', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()])->assertSessionHasNoErrors();
    $this->travel(8)->hours();
    $this->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_out', 'signature' => internshipInk(), 'note' => 'Selesai kegiatan'])->assertSessionHasNoErrors();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('attendance.checked_out_at', '16:15:00')->where('attendance.duration_minutes', 480));
    $this->travel(1)->hours();
    $this->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_out', 'signature' => internshipInk()])->assertSessionHasErrors('action');
    $this->assertDatabaseCount('internship_attendances', 1);
    expect(InternshipAttendance::first()->checked_out_at->format('H:i:s'))->toBe('09:15:00');
});

test('the day changes at midnight WIB rather than midnight UTC', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 23:59:00', 'Asia/Jakarta'));
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()])->assertSessionHasNoErrors();
    $this->travel(2)->minutes();
    $this->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('internship_attendances', ['checked_in_at' => '2026-10-29 17:01:00']);
    expect(InternshipAttendance::latest('id')->first()->attendance_date->toDateString())->toBe('2026-10-30');
    $this->assertDatabaseCount('internship_attendances', 2);
});

test('attendance outside the participants internship period is rejected', function (string $start, string $end) {
    $participant = InternshipParticipant::factory()->create(['start_date' => $start, 'end_date' => $end]);
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()])->assertSessionHasErrors('action');
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([['2026-10-30', '2026-11-13'], ['2026-10-01', '2026-10-28']]);

test('missing or invalid signature and an overlong note cannot be saved', function (array $changes, string $field) {
    $participant = InternshipParticipant::factory()->create();
    $this->withSession(internshipSession($participant))->post(route('internship.submit'), [...[...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()], ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([[['signature' => ''], 'signature'], [['signature' => 'data:image/png;base64,invalid'], 'signature'], [[...internshipPosition(), 'action' => 'unknown'], 'action'], [['note' => str_repeat('x', 501)], 'note']]);

test('an expired or deactivated participant session cannot submit attendance', function (bool $active, int $hours) {
    $participant = InternshipParticipant::factory()->create();
    $this->withSession(internshipSession($participant));
    $participant->update(['is_active' => $active]);
    $this->travel($hours)->hours();
    $this->post(route('internship.submit'), [...internshipPosition(), 'action' => 'check_in', 'signature' => internshipInk()])->assertSessionHasErrors('access');
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([[false, 0], [true, 13]]);

test('switching participant clears the attendance session but not an admin login', function () {
    $participant = InternshipParticipant::factory()->create();
    $admin = internshipAdmin();
    $this->actingAs($admin)->withSession(internshipSession($participant))->post(route('internship.leave'))->assertRedirect(route('internship.show'));
    $this->assertAuthenticatedAs($admin);
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant', null));
});

test('issuing a recovery link invalidates legacy codes and attendance sessions', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->actingAs(internshipAdmin())->withSession(internshipSession($participant))->post(route('internships.recover-access', $participant))->assertSessionHasNoErrors();
    expect($participant->fresh()->access_code_hash)->toBeNull();
    expect(session('internship_issued_access.url'))->toBeString();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant', null));
    $this->post(route('internship.verify'), ['student_number' => $participant->student_number, 'access_code' => 'KODEMAGANG12'])->assertSessionHasErrors('access_code');
});

test('exports and print include all matching records beyond the first page and escape spreadsheet formulae', function () {
    $participant = InternshipParticipant::factory()->create(['student_number' => '000123', 'name' => '=Peserta', 'institution' => '<script>alert(1)</script>']);
    for ($day = 1; $day <= 26; $day++) {
        $participant->attendances()->create(['attendance_date' => sprintf('2026-10-%02d', $day), 'checked_in_at' => sprintf('2026-10-%02d 01:00:00', $day), 'check_in_signature' => substr(internshipInk(), 22), 'check_in_note' => '=1+1']);
    }
    $this->actingAs(internshipAdmin())->get(route('internships.index', ['month' => '2026-10']))->assertInertia(fn (Assert $page) => $page->has('records.data', 25)->where('records.total', 26));
    $csv = $this->get(route('internships.export', ['month' => '2026-10']))->assertOk()->streamedContent();
    expect(substr_count($csv, "'000123"))->toBe(26);
    expect($csv)->toContain("'=Peserta", "'=1+1");
    $this->get(route('internships.print', ['month' => '2026-10']))->assertOk()->assertSee('01/10/2026')->assertSee('26/10/2026')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $empty = $this->get(route('internships.export', ['month' => '2026-11']))->assertOk()->streamedContent();
    expect($empty)->not->toContain("'000123");
});

test('participant signatures are only available to admins', function () {
    $participant = InternshipParticipant::factory()->create();
    $record = $participant->attendances()->create(['attendance_date' => '2026-10-29', 'checked_in_at' => now(), 'check_in_signature' => substr(internshipInk(), 22)]);
    $this->get(route('internships.signature', [$record, 'in']))->assertRedirect(route('login'));
    $this->actingAs(internshipAdmin())->get(route('internships.signature', [$record, 'in']))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('internships.signature', [$record, 'out']))->assertNotFound();
});

test('repeated incorrect codes are rate limited', function () {
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $this->post(route('internship.verify'), ['student_number' => 'TIDAKADA', 'access_code' => 'SALAH'])->assertSessionHasErrors('access_code');
    }
    $this->post(route('internship.verify'), ['student_number' => 'TIDAKADA', 'access_code' => 'SALAH'])->assertStatus(429);
});
