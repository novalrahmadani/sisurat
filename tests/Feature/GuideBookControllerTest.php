<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

test('guests must sign in before opening the guide or its PDF', function (string $routeName) {
    $response = $this->get(route($routeName));

    $response->assertRedirect(route('login'));
})->with(['guide', 'guide.book', 'guide.book.download']);

test('active users can read the guide PDF inline', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $disk = Storage::fake('local');
    $pdf = "%PDF-1.4\nSISURAT guide fixture\n%%EOF";
    $disk->put('guides/Panduan-SISURAT-Tutorial-Bernomor.pdf', $pdf);

    $response = $this->actingAs($user)->get(route('guide.book'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=Panduan-SISURAT-Tutorial-Bernomor.pdf');
    expect($response->baseResponse->getFile()->getContent())->toBe($pdf);
})->with([User::ROLE_ADMIN, User::ROLE_STAFF]);

test('active users can download the guide with a descriptive filename', function () {
    $user = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    $disk = Storage::fake('local');
    $pdf = "%PDF-1.4\nSISURAT download fixture\n%%EOF";
    $disk->put('guides/Panduan-SISURAT-Tutorial-Bernomor.pdf', $pdf);

    $response = $this->actingAs($user)->get(route('guide.book.download'));

    $response->assertOk()
        ->assertDownload('Panduan-SISURAT-Tutorial-Bernomor.pdf')
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->baseResponse->getFile()->getContent())->toBe($pdf);
});

test('missing guide PDFs return 404', function (string $routeName) {
    $user = User::factory()->create(['is_active' => true]);
    Storage::fake('local');

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertNotFound();
})->with(['guide.book', 'guide.book.download']);

test('active users can open the guide page', function () {
    $user = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->get(route('guide'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Guide'));
});
