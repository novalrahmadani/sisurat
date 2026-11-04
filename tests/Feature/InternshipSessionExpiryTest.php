<?php

use App\Models\InternshipParticipant;
use App\Support\InternshipDeviceAccess;
use App\Support\InternshipPinAccess;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->app['env'] = 'csrf-testing';
});

test('an expired PIN form returns to a fresh form without treating the PIN as invalid', function () {
    $participant = InternshipParticipant::factory()->create();
    $pin = app(InternshipPinAccess::class)->issue($participant);
    $hash = $participant->access_code_hash;

    $this->withSession(['_token' => 'new-session-token'])->withHeaders(['X-Inertia' => 'true', 'Accept' => 'application/json'])
        ->post(route('internship.verify-pin'), ['_token' => 'closed-tab-token', 'pin' => $pin])
        ->assertStatus(303)->assertRedirect(route('internship.show'))
        ->assertSessionHas('internship_session_notice')->assertSessionMissing('_old_input')
        ->assertSessionMissing(InternshipDeviceAccess::SESSION);

    expect($participant->fresh()->access_code_hash)->toBe($hash);
    $this->assertDatabaseCount('internship_attendances', 0);
    $this->assertGuest();
});

test('session refresh sets fresh cookies without exposing identity or consuming an issued PIN', function () {
    $this->withSession(['internship_issued_pin' => ['id' => 123, 'pin' => '12345678']]);
    $response = $this->getJson(route('internship.session'));

    $response->assertNoContent()->assertCookie('XSRF-TOKEN')->assertCookie(config('session.cookie'))
        ->assertSessionHas('internship_issued_pin.pin', '12345678');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    expect(session()->token())->toBeString()->not->toBeEmpty();
    $this->assertGuest();
});

test('a closed browser session can obtain a fresh token and open the same participant using PIN alone', function () {
    $participant = InternshipParticipant::factory()->create();
    $pin = app(InternshipPinAccess::class)->issue($participant);
    $this->app['session']->invalidate();

    $this->getJson(route('internship.session'))->assertNoContent();
    $this->post(route('internship.verify-pin'), ['_token' => session()->token(), 'pin' => $pin])
        ->assertSessionHasNoErrors()->assertRedirect(route('internship.show'))
        ->assertSessionHas(InternshipDeviceAccess::SESSION, fn (array $proof) => $proof['id'] === $participant->id);

    $this->assertDatabaseCount('internship_participants', 1);
    $this->assertDatabaseCount('internship_attendances', 0);
    $this->assertGuest();
});

test('a fresh session does not bypass PIN verification', function () {
    $this->getJson(route('internship.session'))->assertNoContent();
    $this->post(route('internship.verify-pin'), ['_token' => session()->token(), 'pin' => '12345678'])
        ->assertSessionHasErrors('pin')->assertSessionMissing(InternshipDeviceAccess::SESSION)
        ->assertSessionMissing('_old_input.pin');
});

test('expired internship forms are safely redirected without processing the attempted action', function (string $name) {
    $this->withSession(['_token' => 'current-token'])
        ->post(route($name), ['_token' => 'stale-token'])
        ->assertStatus(303)->assertRedirect(route('internship.show'))
        ->assertSessionHas('internship_session_notice')->assertSessionMissing('_old_input');
    $this->assertDatabaseCount('internship_participants', 0);
    $this->assertDatabaseCount('internship_attendances', 0);
})->with(['internship.register', 'internship.issue-pin', 'internship.submit', 'internship.leave', 'internship.verify']);

test('an expired recovery form keeps its one time link for explicit resubmission', function () {
    $participant = InternshipParticipant::factory()->create();
    $url = app(InternshipDeviceAccess::class)->recoveryUrl($participant);
    $recoveryHash = $participant->recovery_token_hash;
    $this->withSession(['_token' => 'current-token'])->post($url, ['_token' => 'stale-token'])
        ->assertStatus(303)->assertRedirect($url)->assertSessionHas('internship_session_notice');
    expect($participant->fresh()->recovery_token_hash)->toBe($recoveryHash);
    $this->assertGuest();
});

test('JSON requests with invalid CSRF tokens still fail verification', function () {
    $this->withSession(['_token' => 'current-token'])
        ->postJson(route('internship.verify-pin'), ['_token' => 'stale-token', 'pin' => '12345678'])
        ->assertStatus(419)->assertSessionMissing(InternshipDeviceAccess::SESSION);
    $this->assertGuest();
});
