<?php

use App\Http\Middleware\UseBuiltAssetsForNetworkRequests;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

function prepareNetworkAssetFixtures(): Vite
{
    $disk = Storage::fake('local');
    app()->usePublicPath($disk->path('public'));
    app()->detectEnvironment(fn (): string => 'local');
    config(['inertia.ssr.enabled' => false]);
    $disk->put('vite.hot', 'http://[::1]:5173');
    $disk->put('public/build/manifest.json', json_encode([
        'resources/css/app.css' => ['src' => 'resources/css/app.css', 'file' => 'assets/app-fixture.css', 'isEntry' => true],
        'resources/js/app.ts' => ['src' => 'resources/js/app.ts', 'file' => 'assets/app-fixture.js', 'isEntry' => true],
        'resources/js/pages/auth/Login.vue' => ['src' => 'resources/js/pages/auth/Login.vue', 'file' => 'assets/login-fixture.js', 'isEntry' => true],
    ], JSON_THROW_ON_ERROR));

    return app(Vite::class)->useHotFile($disk->path('vite.hot'));
}

test('network requests load CSS and JavaScript from the SISURAT origin while Vite is running', function (string $origin) {
    $vite = prepareNetworkAssetFixtures();
    $originalHotFile = $vite->hotFile();

    $response = $this->get($origin.'/login');

    $response->assertOk()
        ->assertSee('href="'.$origin.'/build/assets/app-fixture.css"', false)
        ->assertSee('src="'.$origin.'/build/assets/app-fixture.js"', false)
        ->assertSee('src="'.$origin.'/build/assets/login-fixture.js"', false)
        ->assertDontSee('[::1]:5173', false)
        ->assertDontSee('@vite/client', false);
    expect($vite->hotFile())->toBe($originalHotFile);
})->with(['http://192.168.1.72:8000', 'http://sisurat.lan:8000', 'http://[fd00::1234]:8000']);

test('loopback development continues to use Vite', function (string $origin) {
    prepareNetworkAssetFixtures();

    $this->get($origin.'/login')->assertOk()
        ->assertSee('http://[::1]:5173/@vite/client', false)
        ->assertDontSee('/build/assets/app-fixture.css', false);
})->with(['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000']);

test('network asset selection does not leak into subsequent localhost requests', function () {
    prepareNetworkAssetFixtures();

    $this->get('http://192.168.1.72:8000/login')->assertOk()
        ->assertDontSee('@vite/client', false);
    $this->get('http://localhost:8000/login')->assertOk()
        ->assertSee('http://[::1]:5173/@vite/client', false);
});

test('normal built assets still load when no Vite server is running', function () {
    $vite = prepareNetworkAssetFixtures();
    $vite->useHotFile(Storage::disk('local')->path('missing.hot'));

    $this->get('http://192.168.1.72:8000/login')->assertOk()
        ->assertSee('/build/assets/app-fixture.css', false)
        ->assertDontSee('@vite/client', false);
});

test('asset selection outside the local environment stays unchanged', function () {
    prepareNetworkAssetFixtures();
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('http://192.168.1.72:8000/login')->assertOk()
        ->assertSee('http://[::1]:5173/@vite/client', false);
});

test('Vite configuration is restored even when the request fails', function () {
    $vite = prepareNetworkAssetFixtures();
    $originalHotFile = $vite->hotFile();
    $middleware = app(UseBuiltAssetsForNetworkRequests::class);

    expect(fn () => $middleware->handle(
        Request::create('http://192.168.1.72:8000/login'),
        fn () => throw new RuntimeException('Downstream request failed'),
    ))->toThrow(RuntimeException::class, 'Downstream request failed');

    expect($vite->hotFile())->toBe($originalHotFile);
});
