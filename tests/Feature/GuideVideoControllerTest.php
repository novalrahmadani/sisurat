<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('guests cannot access tutorial media', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['guide.video', 'guide.video.download', 'guide.video.poster']);

test('active admins and staff can play the tutorial inline', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $disk = Storage::fake('local');
    $video = str_repeat('SISURAT video fixture ', 10);
    $disk->put('guides/Tutorial-SISURAT.mp4', $video);

    $response = $this->actingAs($user)->get(route('guide.video'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Content-Disposition', 'inline; filename=Tutorial-SISURAT.mp4')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Accept-Ranges', 'bytes');
    expect($response->baseResponse->getFile()->getContent())->toBe($video);
})->with([User::ROLE_ADMIN, User::ROLE_STAFF]);

test('the video supports byte ranges for seeking without fetching the entire file', function () {
    $user = User::factory()->create(['is_active' => true]);
    $disk = Storage::fake('local');
    $disk->put('guides/Tutorial-SISURAT.mp4', str_repeat('x', 128));

    $this->actingAs($user)->withHeader('Range', 'bytes=32-63')->get(route('guide.video'))
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 32-63/128')
        ->assertHeader('Content-Length', '32');
});

test('the tutorial downloads with an MP4 filename', function () {
    $user = User::factory()->create(['is_active' => true]);
    Storage::fake('local')->put('guides/Tutorial-SISURAT.mp4', 'video fixture');

    $this->actingAs($user)->get(route('guide.video.download'))
        ->assertOk()->assertDownload('Tutorial-SISURAT.mp4')->assertHeader('Content-Type', 'video/mp4');
});

test('the tutorial poster is served to signed-in users', function () {
    $user = User::factory()->create(['is_active' => true]);
    Storage::fake('local')->put('guides/Tutorial-SISURAT-poster.png', 'poster fixture');

    $this->actingAs($user)->get(route('guide.video.poster'))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('missing tutorial media returns not found', function (string $routeName) {
    $user = User::factory()->create(['is_active' => true]);
    Storage::fake('local');

    $this->actingAs($user)->get(route($routeName))->assertNotFound();
})->with(['guide.video', 'guide.video.download', 'guide.video.poster']);
