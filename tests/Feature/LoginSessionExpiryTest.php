<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    // Exercise CSRF verification, which Laravel normally skips in tests.
    $this->app['env'] = 'csrf-testing';
});

test('expired login forms return to login with a message and do not authenticate', function (array $headers, array $payload) {
    $response = $this->withSession(['_token' => 'current-session-token'])
        ->withHeaders($headers)
        ->post(route('login.store'), $payload);

    $response->assertStatus(303)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Halaman masuk sudah kedaluwarsa. Silakan masukkan kembali email dan kata sandi.')
        ->assertSessionMissing('_old_input');
    $this->assertGuest();
})->with([
    'missing token' => [[], []],
    'stale token' => [[], ['_token' => 'old-token', 'email' => 'expired@example.test', 'password' => 'not-saved']],
    'Inertia stale token' => [['X-Inertia' => 'true', 'Accept' => 'application/json'], ['_token' => 'old-token']],
]);

test('JSON login requests with an invalid token still return 419', function () {
    $response = $this->withSession(['_token' => 'current-session-token'])
        ->postJson(route('login.store'), ['_token' => 'old-token']);

    $response->assertStatus(419);
    $this->assertGuest();
});

test('a fresh login token allows valid credentials to authenticate', function () {
    $user = User::factory()->create(['is_active' => true]);

    $response = $this->withSession(['_token' => 'fresh-session-token'])
        ->post(route('login.store'), [
            '_token' => 'fresh-session-token',
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('other forms with an invalid token are still rejected with 419', function () {
    $response = $this->withSession(['_token' => 'current-session-token'])
        ->post(route('password.email'), ['_token' => 'old-token']);

    $response->assertStatus(419);
    $this->assertGuest();
});
