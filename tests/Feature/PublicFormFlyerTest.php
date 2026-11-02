<?php

use App\Models\AttendanceEvent;
use App\Models\InterviewForm;
use App\Models\QuranExam;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function flyerActivity(string $kind, User $owner): array
{
    $activity = match ($kind) {
        'attendance' => AttendanceEvent::query()->create([
            'created_by' => $owner->id, 'title' => 'Rapat Persiapan PMB Tahun Akademik 2027/2028', 'type' => 'meeting',
            'event_date' => '2026-10-30', 'public_token' => Str::random(64), 'is_open' => true,
        ]),
        'interviews' => InterviewForm::query()->create([
            'created_by' => $owner->id, 'title' => 'Wawancara Gelombang 1', 'questions' => [],
            'questions_version' => (string) Str::uuid(), 'public_token' => Str::random(64), 'is_open' => true,
        ]),
        'quran-exams' => QuranExam::factory()->create(['created_by' => $owner->id, 'title' => 'Tes Baca Al-Quran Gelombang 1']),
        default => null,
    };
    $filename = match ($kind) {
        'attendance' => 'flyer-daftar-hadir-'.$activity->id.'.png',
        'interviews' => 'flyer-wawancara-'.$activity->id.'.png',
        'quran-exams' => 'flyer-penguji-mq-'.$activity->id.'.png',
        default => 'flyer-absensi-magang.png',
    };

    return [route($kind.'.flyer', $activity), $filename, $activity];
}

test('authorized admins preview and download complete PNG flyers for all shared forms', function (string $kind) {
    config(['internship.public_url' => 'https://sisurat.example']);
    $owner = User::factory()->create(['is_active' => true, 'role' => User::ROLE_ADMIN]);
    [$url, $filename] = flyerActivity($kind, $owner);
    $preview = $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename="'.$filename.'"')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($preview->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $dimensions = getimagesizefromstring($preview->getContent());
    expect([$dimensions[0], $dimensions[1], $dimensions[2]])->toBe([1080, 1350, IMAGETYPE_PNG]);
    $download = $this->get($url.'?download=1')->assertOk()->assertDownload($filename);
    expect($download->getContent())->toBe($preview->getContent());
    if ($directory = getenv('SISURAT_FLYER_OUTPUT_DIR')) {
        file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $preview->getContent());
    }
})->with(['attendance', 'interviews', 'quran-exams', 'internships']);

test('flyers require login and cannot be generated for another owners private form', function (string $kind) {
    $owner = User::factory()->create(['is_active' => true, 'role' => User::ROLE_ADMIN]);
    [$url] = flyerActivity($kind, $owner);
    $this->get($url)->assertRedirect(route('login'));
    $other = User::factory()->create(['is_active' => true, 'role' => User::ROLE_STAFF]);
    $this->actingAs($other)->get($url)->assertForbidden();
})->with(['attendance', 'interviews', 'quran-exams', 'internships']);

test('flyers automatically refresh titles and public destinations while ignoring URL overrides', function () {
    $owner = User::factory()->create(['is_active' => true]);
    [$url, $filename, $activity] = flyerActivity('interviews', $owner);
    config(['internship.public_url' => 'https://sisurat-one.example']);
    $first = $this->actingAs($owner)->get($url)->assertOk()->getContent();
    $activity->update(['title' => 'Wawancara Gelombang 2']);
    $second = $this->get($url)->assertOk()->getContent();
    expect($second)->not->toBe($first);
    config(['internship.public_url' => 'https://sisurat-two.example']);
    $third = $this->get($url)->assertOk()->getContent();
    expect($third)->not->toBe($second);
    expect($this->get($url.'?url=https://unrelated.example')->assertOk()->getContent())->toBe($third);
});

test('long activity titles still produce valid flyers', function () {
    $owner = User::factory()->create(['is_active' => true]);
    [$url, $filename, $activity] = flyerActivity('attendance', $owner);
    $activity->update(['title' => str_repeat('W', 150)]);
    $response = $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $dimensions = getimagesizefromstring($response->getContent());
    expect([$dimensions[0], $dimensions[1]])->toBe([1080, 1350]);
    if ($directory = getenv('SISURAT_FLYER_OUTPUT_DIR')) {
        file_put_contents($directory.DIRECTORY_SEPARATOR.'flyer-judul-panjang.png', $response->getContent());
    }
});
