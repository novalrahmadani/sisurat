<?php

use App\Models\InternshipParticipant;
use App\Support\InternshipDeviceAccess;
use App\Support\InternshipPinAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 08:15:00', 'Asia/Jakarta'));
});

function participantWithPin(array $attributes = []): array
{
    $participant = InternshipParticipant::factory()->create($attributes);

    return [$participant, app(InternshipPinAccess::class)->issue($participant)];
}

function pinDeviceSession(InternshipParticipant $participant): array
{
    $participant->update(['device_token_hash' => hash('sha256', str_repeat('a', 64)), 'device_expires_at' => now()->addDays(180)]);

    return [InternshipDeviceAccess::SESSION => ['id' => $participant->id, 'fingerprint' => $participant->device_token_hash]];
}

test('registration automatically issues a private eight digit PIN and displays it once', function () {
    $this->post(route('internship.register'), [
        'name' => 'Peserta PIN', 'participant_category' => 'smk', 'student_number' => 'NIS-123',
        'institution' => 'SMK Contoh', 'start_date' => '2026-10-05', 'end_date' => '2026-11-13',
        'pin_lookup_hash' => 'injected', 'access_code_hash' => 'injected',
    ])->assertSessionHasNoErrors()->assertRedirect(route('internship.show'));
    $issued = session('internship_issued_pin');
    $participant = InternshipParticipant::firstOrFail();
    expect($issued['pin'])->toMatch('/^[0-9]{8}$/D');
    expect($issued['id'])->toBe($participant->id);
    expect(Hash::check($issued['pin'], $participant->access_code_hash))->toBeTrue();
    expect($participant->pin_lookup_hash)->toBe(app(InternshipPinAccess::class)->fingerprint($issued['pin']));
    expect($participant->getAttributes())->not->toHaveKey('pin');
    expect(json_encode($participant->toArray()))->not->toContain($issued['pin'])->not->toContain($participant->pin_lookup_hash)->not->toContain($participant->access_code_hash);
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page
        ->where('participant.name', 'Peserta PIN')->where('participant.has_pin', true)
        ->where('issuedPin', $issued['pin'])->where('participant.can_attend', true)
        ->missing('participant.pin_lookup_hash')->missing('participant.access_code_hash'));
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('issuedPin', null));
    $this->assertGuest();
});

test('PIN alone identifies the correct participant despite duplicated school numbers', function () {
    [$first] = participantWithPin(['student_number' => '123', 'institution' => 'Sekolah Satu', 'participant_category' => 'smk']);
    [$participant, $pin] = participantWithPin(['student_number' => '123', 'institution' => 'Sekolah Dua', 'participant_category' => 'smk']);
    $oldProof = pinDeviceSession($participant);
    $this->post(route('internship.verify-pin'), ['pin' => $pin])
        ->assertSessionHasNoErrors()->assertRedirect(route('internship.show'))
        ->assertCookie(InternshipDeviceAccess::COOKIE)
        ->assertSessionHas(InternshipDeviceAccess::SESSION, fn (array $proof) => $proof['id'] === $participant->id);
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page
        ->where('participant.id', $participant->id)->where('participant.institution', 'Sekolah Dua')
        ->where('issuedPin', null)->missing('participants'));
    expect($participant->fresh()->device_token_hash)->not->toBe($oldProof[InternshipDeviceAccess::SESSION]['fingerprint']);
    expect($first->fresh()->device_token_hash)->toBeNull();
    $this->assertDatabaseCount('internship_participants', 2);
    $this->assertDatabaseCount('internship_attendances', 0);
    $this->assertGuest();
});

test('invalid PIN input is rejected without leaking it into old input', function (mixed $pin) {
    $this->from(route('internship.show'))->post(route('internship.verify-pin'), ['pin' => $pin])
        ->assertSessionHasErrors('pin')->assertSessionMissing('_old_input.pin')
        ->assertSessionMissing(InternshipDeviceAccess::SESSION)->assertCookieMissing(InternshipDeviceAccess::COOKIE);
})->with(['short' => ['1234567'], 'long' => ['123456789'], 'letters' => ['abcdefgh'], 'array' => [['12345678']], 'number' => [12345678], 'missing' => [null], 'newline' => ["12345678\n"]]);

test('wrong inactive or revoked PIN never grants attendance access', function (string $state) {
    [$participant, $pin] = participantWithPin();
    if ($state === 'inactive') {
        $participant->update(['is_active' => false]);
    }
    if ($state === 'revoked') {
        $participant->update(['access_code_hash' => null]);
    }
    if ($state === 'wrong') {
        $participant->update(['access_code_hash' => Hash::make('not-the-pin')]);
    }
    $this->post(route('internship.verify-pin'), ['pin' => $pin])
        ->assertSessionHasErrors(['pin' => 'PIN tidak sesuai atau peserta tidak aktif. Periksa kembali PIN Anda.'])
        ->assertSessionMissing('_old_input.pin')->assertSessionMissing(InternshipDeviceAccess::SESSION);
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant', null)->where('issuedPin', null));
})->with(['inactive', 'revoked', 'wrong']);

test('PIN attempts are limited for both repeated and changing guesses', function (bool $change) {
    $limit = $change ? 30 : 6;
    for ($i = 0; $i < $limit; $i++) {
        $this->post(route('internship.verify-pin'), ['pin' => (string) (12345678 + ($change ? $i : 0))])->assertSessionHasErrors('pin');
    }
    $this->post(route('internship.verify-pin'), ['pin' => (string) (12345678 + ($change ? $limit : 0))])->assertStatus(429);
})->with([false, true]);

test('an existing recognized participant creates a PIN without admin intervention', function () {
    $participant = InternshipParticipant::factory()->create(['access_code_hash' => null]);
    $this->withSession(pinDeviceSession($participant))->post(route('internship.issue-pin'))->assertSessionHasNoErrors();
    $issued = session('internship_issued_pin');
    expect($issued['id'])->toBe($participant->id);
    expect($issued['pin'])->toMatch('/^[0-9]{8}$/D');
    expect(Hash::check($issued['pin'], $participant->fresh()->access_code_hash))->toBeTrue();
    $this->assertDatabaseCount('internship_participants', 1);
    $this->assertGuest();
});

test('replacement invalidates the old PIN and survives switching participants', function () {
    [$participant, $oldPin] = participantWithPin();
    [$other, $otherPin] = participantWithPin();
    $otherFingerprint = $other->pin_lookup_hash;
    $this->withSession(pinDeviceSession($participant))->post(route('internship.issue-pin'), ['participant_id' => $other->id])->assertSessionHasNoErrors();
    $newPin = session('internship_issued_pin.pin');
    expect($newPin)->not->toBe($oldPin)->not->toBe($otherPin);
    expect($other->fresh()->pin_lookup_hash)->toBe($otherFingerprint);
    $this->post(route('internship.leave'))->assertSessionMissing('internship_issued_pin')->assertSessionMissing(InternshipDeviceAccess::SESSION);
    $this->post(route('internship.verify-pin'), ['pin' => $oldPin])->assertSessionHasErrors('pin');
    $this->post(route('internship.verify-pin'), ['pin' => $newPin])->assertSessionHasNoErrors();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant.id', $participant->id));
});

test('unrecognized or inactive visitors cannot create a PIN by posting an identity', function (bool $inactive) {
    $participant = InternshipParticipant::factory()->create();
    if ($inactive) {
        $this->withSession(pinDeviceSession($participant));
        $participant->update(['is_active' => false]);
    }
    $this->post(route('internship.issue-pin'), ['participant_id' => $participant->id, 'student_number' => $participant->student_number])
        ->assertSessionHasErrors('access')->assertSessionMissing('internship_issued_pin');
    expect($participant->fresh()->pin_lookup_hash)->toBeNull();
})->with([false, true]);

test('PIN creation throttle survives session regeneration', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->withSession(pinDeviceSession($participant));
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('internship.issue-pin'))->assertSessionHasNoErrors();
    }
    $this->post(route('internship.issue-pin'))->assertStatus(429);
});

test('PIN login cancels a pending recovery link without device approval', function () {
    [$participant, $pin] = participantWithPin();
    $url = app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    $this->post(route('internship.verify-pin'), ['pin' => $pin])->assertSessionHasNoErrors();
    $this->post($url)->assertSessionHasErrors('access');
    expect($participant->fresh()->recovery_token_hash)->toBeNull();
});

test('recovered legacy participants create a PIN for subsequent independent access', function () {
    $participant = InternshipParticipant::factory()->create(['access_code_hash' => null]);
    $url = app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    $this->post($url)->assertSessionHasNoErrors();
    $this->post(route('internship.issue-pin'))->assertSessionHasNoErrors();
    $pin = session('internship_issued_pin.pin');
    $this->post(route('internship.leave'));
    $this->post(route('internship.verify-pin'), ['pin' => $pin])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('internship_participants', 1);
});

test('the same PIN remains valid after browser data and the remembered device have expired', function () {
    [$participant, $pin] = participantWithPin();
    $this->withSession(pinDeviceSession($participant))->get(route('internship.show'))->assertOk();
    $this->app['session']->invalidate();
    $this->travel(200)->days();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page->where('participant', null));
    $this->post(route('internship.verify-pin'), ['pin' => $pin])->assertSessionHasNoErrors();
    $this->get(route('internship.show'))->assertInertia(fn (Assert $page) => $page
        ->where('participant.id', $participant->id)->where('participant.can_attend', false));
    $this->assertDatabaseCount('internship_participants', 1);
    $this->assertGuest();
});
