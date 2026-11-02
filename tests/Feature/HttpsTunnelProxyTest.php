<?php

use App\Support\InternshipDeviceAccess;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
});

test('tunnel worker recognizes HTTPS only from the local connector', function (string $peer, string $expectedScheme) {
    $previous = getenv('SISURAT_HTTPS_TUNNEL');
    try {
        putenv('SISURAT_HTTPS_TUNNEL=1');
        $this->refreshApplication();
        $this->withoutVite();
        Route::get('test-tunnel-context', fn (Request $request) => response()->json(['scheme' => $request->getScheme(), 'ip' => $request->ip()]));
        $this->withServerVariables(['REMOTE_ADDR' => $peer])->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.10'])
            ->get('http://sisurat.example/test-tunnel-context')->assertOk()->assertJsonPath('scheme', $expectedScheme);
    } finally {
        $previous === false ? putenv('SISURAT_HTTPS_TUNNEL') : putenv('SISURAT_HTTPS_TUNNEL='.$previous);
        TrustProxies::flushState();
    }
})->with(['connector' => ['127.0.0.1', 'https'], 'untrusted visitor' => ['192.168.1.44', 'http']]);

test('public attendance and recovery links use configured HTTPS without changing the current admin address', function () {
    config(['internship.public_url' => 'https://sisurat.example']);
    $access = app(InternshipDeviceAccess::class);
    expect($access->publicUrl('internship.show'))->toBe('https://sisurat.example/absensi-magang');
    expect($access->publicUrl('internship.recovery', ['token' => str_repeat('a', 64)]))->toBe('https://sisurat.example/absensi-magang/akses/'.str_repeat('a', 64));
});

test('an invalid public URL never creates a script link', function () {
    config(['internship.public_url' => 'javascript:alert(1)']);
    expect(app(InternshipDeviceAccess::class)->publicUrl('internship.show'))->toBe(route('internship.show'));
});
