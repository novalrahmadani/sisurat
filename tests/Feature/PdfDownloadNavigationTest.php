<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\InterviewForm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function pdfNavigationInterview(User $owner): InterviewForm
{
    return InterviewForm::query()->create(['title' => 'Wawancara PDF', 'is_open' => true,
        'questions' => [['id' => (string) Str::uuid(), 'prompt' => 'Mengapa memilih UNUHA?', 'required' => true]],
        'questions_version' => (string) Str::uuid(), 'public_token' => Str::random(64), 'created_by' => $owner->id]);
}

test('Inertia requests are redirected to native PDF downloads instead of receiving PDF bytes', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true]);
    $form = pdfNavigationInterview($owner);
    $route = match ($kind) {
        'interview' => route('interviews.pdf', $form),
        'quran' => route('quran-tests.pdf', ['q' => 'Peserta', 'result' => 'mq2', 'page' => 3]),
        default => route('outgoing-letters.pdf', ['year' => '', 'type' => 'finance', 'source' => 'manual', 'inline' => 1]),
    };
    $version = app(HandleInertiaRequests::class)->version(Request::create($route));
    $response = $this->actingAs($owner)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get($route);
    $response->assertStatus(409)->assertHeader('X-Inertia-Location');
    expect($response->getContent())->toBe('');
    parse_str(parse_url($response->headers->get('X-Inertia-Location'), PHP_URL_QUERY) ?? '', $query);
    expect(parse_url($response->headers->get('X-Inertia-Location'), PHP_URL_PATH))->toBe(parse_url($route, PHP_URL_PATH));
    if ($kind === 'quran') {
        expect($query['q'])->toBe('Peserta')->and($query['result'])->toBe('mq2');
    } elseif ($kind === 'agenda') {
        expect($query['year'])->toBe('')->and($query['type'])->toBe('finance')->and($query['source'])->toBe('manual')->and($query['inline'])->toBe('1');
    }
})->with(['quran', 'interview', 'agenda']);

test('native requests still receive real downloadable PDF documents', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true]);
    $form = pdfNavigationInterview($owner);
    $url = match ($kind) {
        'interview' => route('interviews.pdf', $form),
        'quran' => route('quran-tests.pdf'),
        default => route('outgoing-letters.pdf', ['type' => 'finance']),
    };
    $response = $this->actingAs($owner)->get($url);
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload();
    expect($response->getContent())->toStartWith('%PDF-');
})->with(['quran', 'interview', 'agenda']);

test('PDF navigation does not bypass ownership of private interview reports', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);
    $form = pdfNavigationInterview($owner);
    $url = route('interviews.pdf', $form);
    $version = app(HandleInertiaRequests::class)->version(Request::create($url));
    $this->actingAs($other)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get($url)->assertForbidden();
});
