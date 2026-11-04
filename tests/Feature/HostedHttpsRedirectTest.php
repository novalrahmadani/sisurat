<?php

use App\Models\InternshipParticipant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Route::get('test-https-context', fn (Request $request) => response()->json([
        'scheme' => $request->getScheme(), 'host' => $request->getHost(),
        'attendance_url' => route('internship.show'), 'login_url' => route('login'),
    ]));
});

test('entering the hosted domain over HTTP upgrades to HTTPS and preserves the path and query', function (string $path) {
    $this->get('http://office-pmb.unuha.ac.id'.$path)
        ->assertStatus(308)->assertRedirect('https://office-pmb.unuha.ac.id'.$path)
        ->assertHeaderMissing('Set-Cookie');
})->with(['/', '/informasi', '/absensi-magang', '/login', '/absensi-magang?tujuan=tes%20magang&mode=masuk']);

test('old HTTP Inertia tabs switch to a full HTTPS page instead of making a cross origin visit', function () {
    $this->withHeaders(['X-Inertia' => 'true'])->get('http://office-pmb.unuha.ac.id/absensi-magang?mode=masuk')
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://office-pmb.unuha.ac.id/absensi-magang?mode=masuk');
});

test('an insecure Inertia PIN submission opens the HTTPS form without processing or replaying the PIN', function () {
    $participant = InternshipParticipant::factory()->create();
    $this->withHeaders(['X-Inertia' => 'true'])->post('http://office-pmb.unuha.ac.id/absensi-magang/masuk', ['pin' => '12345678'])
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://office-pmb.unuha.ac.id/absensi-magang')
        ->assertHeaderMissing('Set-Cookie');
    expect($participant->fresh()->device_token_hash)->toBeNull();
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('ordinary HTTP form submissions are upgraded before the application processes them', function () {
    $this->post('http://office-pmb.unuha.ac.id/absensi-magang/daftar', [])
        ->assertStatus(308)->assertRedirect('https://office-pmb.unuha.ac.id/absensi-magang/daftar');
    $this->assertDatabaseCount('internship_participants', 0);
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('HTTPS pages keep navigation URLs secure even when APP_URL still uses HTTP', function () {
    config(['app.url' => 'http://localhost']);
    $this->get('https://office-pmb.unuha.ac.id/test-https-context')
        ->assertOk()->assertJsonPath('scheme', 'https')
        ->assertJsonPath('attendance_url', 'https://office-pmb.unuha.ac.id/absensi-magang')
        ->assertJsonPath('login_url', 'https://office-pmb.unuha.ac.id/login');
});

test('hosted HTTPS pages open without a redirect loop', function (string $path) {
    $this->get('https://office-pmb.unuha.ac.id'.$path)->assertOk()->assertHeaderMissing('Location');
})->with(['/', '/absensi-magang', '/login']);

test('Cloudflare IPv4 and IPv6 peers convey the browser HTTPS scheme without redirect loops', function (string $peer) {
    $this->withServerVariables(['REMOTE_ADDR' => $peer])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])
        ->get('http://office-pmb.unuha.ac.id/test-https-context')
        ->assertOk()->assertJsonPath('scheme', 'https')->assertJsonPath('host', 'office-pmb.unuha.ac.id')
        ->assertJsonPath('attendance_url', 'https://office-pmb.unuha.ac.id/absensi-magang');
})->with(['IPv4' => '173.245.48.12', 'IPv6' => '2606:4700::1234']);

test('a browser HTTP request through Cloudflare still upgrades when the origin itself uses TLS', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.12'])
        ->withHeaders(['X-Forwarded-Proto' => 'http'])
        ->get('https://office-pmb.unuha.ac.id/absensi-magang')
        ->assertStatus(308)->assertRedirect('https://office-pmb.unuha.ac.id/absensi-magang');
});

test('untrusted visitors cannot bypass HTTPS or change the redirect host using proxy headers', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])
        ->get('http://office-pmb.unuha.ac.id/absensi-magang')
        ->assertStatus(308)->assertRedirect('https://office-pmb.unuha.ac.id/absensi-magang');
});

test('localhost LAN and other development hosts continue using HTTP', function (string $origin) {
    $this->get($origin.'/test-https-context')->assertOk()->assertJsonPath('scheme', 'http');
})->with(['http://localhost:8000', 'http://127.0.0.1:8000', 'http://192.168.1.72:8000', 'http://sisurat.test', 'http://office-pmb.unuha.ac.id.attacker.example']);
