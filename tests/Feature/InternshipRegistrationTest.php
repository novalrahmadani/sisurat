<?php

use App\Models\InternshipParticipant;
use App\Models\User;
use App\Support\InternshipDeviceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 08:15:00', 'Asia/Jakarta'));
});

function registrationData(): array
{
    return ['name' => 'Peserta Mandiri', 'participant_category' => 'smk', 'student_number' => '000123',
        'institution' => 'SMK Contoh', 'study_program' => 'Rekayasa Perangkat Lunak', 'class_level' => 'XI',
        'start_date' => '2026-10-01', 'end_date' => '2026-11-13'];
}

function registeredDeviceParticipant(array $attributes = []): InternshipParticipant
{
    return InternshipParticipant::factory()->create([...$attributes,
        'device_token_hash' => hash('sha256', str_repeat('a', 64)), 'device_expires_at' => now()->addDays(180)]);
}

test('visitors register school and university categories without an account or a typed code', function (string $category) {
    $response = $this->post(route('internship.register'), [...registrationData(), 'participant_category' => $category, 'is_active' => false, 'role' => 'admin', 'registration_source' => 'admin', 'device_token_hash' => 'injected']);
    $response->assertSessionHasNoErrors()->assertRedirect(route('internship.show'))->assertCookie(InternshipDeviceAccess::COOKIE);
    $participant = InternshipParticipant::firstOrFail();
    expect($participant->participant_category)->toBe($category);
    expect($participant->is_active)->toBeTrue();
    expect($participant->registration_source)->toBe('public');
    expect($participant->device_token_hash)->toHaveLength(64)->not->toBe('injected');
    expect($participant->access_code_hash)->toBeNull();
    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
})->with(['smk', 'sma', 'university']);

test('registration requires complete identity and valid period dates', function () {
    $this->post(route('internship.register'), [])->assertSessionHasErrors(['name', 'participant_category', 'student_number', 'institution', 'start_date', 'end_date']);
    $this->post(route('internship.register'), [...registrationData(), 'end_date' => '2026-09-30'])->assertSessionHasErrors('end_date');
    $this->assertDatabaseCount('internship_participants', 0);
});

test('invalid categories and malformed identity fields are rejected without server errors', function (array $changes, string $field) {
    $this->post(route('internship.register'), [...registrationData(), ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('internship_participants', 0);
})->with([
    [['participant_category' => 'staff'], 'participant_category'],
    [['participant_category' => ['smk']], 'participant_category'],
    [['institution' => ['invalid']], 'institution'],
    [['student_number' => ['invalid']], 'student_number'],
    [['class_level' => str_repeat('x', 51)], 'class_level'],
]);

test('registration cannot overwrite or claim an existing identity', function () {
    $participant = InternshipParticipant::factory()->create(registrationData());
    $this->post(route('internship.register'), [...registrationData(), 'name' => 'Nama Pengganti'])->assertSessionHasErrors('student_number')->assertCookieMissing(InternshipDeviceAccess::COOKIE);
    expect($participant->fresh()->name)->toBe('Peserta Mandiri');
    $this->assertDatabaseCount('internship_participants', 1);
});

test('the same school number can exist at another institution without a false duplicate', function () {
    InternshipParticipant::factory()->create(registrationData());
    $this->post(route('internship.register'), [...registrationData(), 'institution' => 'SMK Berbeda'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('internship_participants', 2);
});

test('an existing participant awaiting categorization cannot be duplicated by public registration', function () {
    $participant = InternshipParticipant::factory()->create([...registrationData(), 'participant_category' => null]);
    $this->post(route('internship.register'), registrationData())->assertSessionHasErrors('student_number');
    expect($participant->fresh()->participant_category)->toBeNull();
    $this->assertDatabaseCount('internship_participants', 1);
});

test('remembered device opens only its own participant even after the ordinary session is lost', function () {
    $participant = registeredDeviceParticipant(['name' => 'Identitas Pribadi']);
    InternshipParticipant::factory()->create(['name' => 'Identitas Peserta Lain']);
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('a', 64))->get(route('internship.show'))
        ->assertInertia(fn (Assert $page) => $page->where('participant.name', 'Identitas Pribadi')->missing('participants')->missing('participant.device_token_hash')->missing('participant.recovery_token_hash'));
    $this->assertGuest();
});

test('a modified or expired device proof cannot open participant data', function (bool $expired) {
    $participant = registeredDeviceParticipant();
    if ($expired) {
        $participant->update(['device_expires_at' => now()->subMinute()]);
    }
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat($expired ? 'a' : 'b', 64))->get(route('internship.show'))
        ->assertInertia(fn (Assert $page) => $page->where('participant', null));
})->with([false, true]);

test('an already identified device does not create another participant accidentally', function () {
    $participant = registeredDeviceParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('a', 64))
        ->post(route('internship.register'), registrationData())->assertRedirect(route('internship.show'));
    $this->assertDatabaseCount('internship_participants', 1);
});

test('recovery links require confirmation and are usable once without an admin account', function () {
    $participant = registeredDeviceParticipant();
    $url = app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('recovery.available', true)->missing('participants'));
    expect($participant->fresh()->recovery_token_hash)->not->toBeNull();
    $this->post($url)->assertSessionHasNoErrors()->assertRedirect(route('internship.show'))->assertCookie(InternshipDeviceAccess::COOKIE);
    expect($participant->fresh()->recovery_token_hash)->toBeNull();
    $this->assertGuest();
    $this->post($url)->assertSessionHasErrors('access');
});

test('expired and replaced recovery links cannot recover a device', function (bool $expired) {
    $participant = registeredDeviceParticipant();
    $url = app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    if ($expired) {
        $this->travel(31)->minutes();
    } else {
        app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    }
    $this->post($url)->assertSessionHasErrors('access')->assertCookieMissing(InternshipDeviceAccess::COOKIE);
})->with([true, false]);

test('releasing device access revokes that proof while preserving an admin login', function () {
    $participant = registeredDeviceParticipant();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($admin)->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('a', 64))
        ->post(route('internship.leave'))->assertRedirect(route('internship.show'))->assertCookieExpired(InternshipDeviceAccess::COOKIE);
    expect($participant->fresh()->device_token_hash)->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

test('repeated public registration attempts are rate limited', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('internship.register'), [])->assertSessionHasErrors('name');
    }
    $this->post(route('internship.register'), [])->assertStatus(429);
});
