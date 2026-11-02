<?php

use App\Models\AttendanceEvent;
use App\Models\InterviewForm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function shareAttendance(User $owner, string $type = 'meeting'): AttendanceEvent
{
    return AttendanceEvent::query()->create([
        'title' => 'Kegiatan PMB', 'type' => $type, 'event_date' => '2026-10-29',
        'public_token' => Str::random(64), 'is_open' => true, 'created_by' => $owner->id,
    ]);
}

function shareInterview(User $owner): InterviewForm
{
    return InterviewForm::query()->create([
        'title' => 'Wawancara PMB', 'questions' => [], 'questions_version' => (string) Str::uuid(),
        'public_token' => Str::random(64), 'is_open' => true, 'created_by' => $owner->id,
    ]);
}

test('attendance sharing uses the public HTTPS address for every event category', function (string $type) {
    config(['internship.public_url' => 'https://sisurat-public.example/']);
    $owner = User::factory()->create(['is_active' => true]);
    $event = shareAttendance($owner, $type);

    $this->actingAs($owner)->get(route('attendance.show', $event))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('attendance/Show')
            ->where('public_url', 'https://sisurat-public.example/hadir/'.$event->public_token));
})->with(['meeting', 'pmb_test', 'other']);

test('interview and internship sharing also use the configured public HTTPS address', function () {
    config(['internship.public_url' => 'https://sisurat-public.example']);
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $interview = shareInterview($owner);

    $this->actingAs($owner)->get(route('interviews.show', $interview))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('interviews/Show')
            ->where('public_url', 'https://sisurat-public.example/wawancara/'.$interview->public_token));
    $this->get(route('internships.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('internship/Index')
            ->where('publicAttendanceUrl', 'https://sisurat-public.example/absensi-magang'));
});

test('form links fall back to the current site when no valid public HTTPS address is configured', function (?string $base) {
    config(['internship.public_url' => $base]);
    $owner = User::factory()->create(['is_active' => true]);
    $event = shareAttendance($owner);

    $this->actingAs($owner)->get(route('attendance.show', $event))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('attendance/Show')
            ->where('public_url', route('attendance.join', ['token' => $event->public_token])));
})->with([null, 'http://sisurat-public.example', 'invalid-url']);

test('authorized users can display and download valid PNG QR images for their public forms', function (string $kind) {
    config(['internship.public_url' => 'https://sisurat-public.example']);
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($owner);
    [$url, $filename] = match ($kind) {
        'attendance' => [route('attendance.qr', $event = shareAttendance($owner)), 'qr-daftar-hadir-'.$event->id.'.png'],
        'interview' => [route('interviews.qr', $interview = shareInterview($owner)), 'qr-wawancara-'.$interview->id.'.png'],
        'internship' => [route('internships.qr'), 'qr-absensi-magang.png'],
    };

    $image = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename="'.$filename.'"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($image->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $dimensions = getimagesizefromstring($image->getContent());
    expect($dimensions[0])->toBe(768);
    expect($dimensions[1])->toBe(768);
    expect($dimensions[2])->toBe(IMAGETYPE_PNG);
    $download = $this->get($url.'?download=1')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
    expect($download->getContent())->toBe($image->getContent());
})->with(['attendance', 'interview', 'internship']);

test('QR images require login and do not expose another owners form', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $event = shareAttendance($owner);
    $interview = shareInterview($owner);
    foreach ([route('attendance.qr', $event), route('interviews.qr', $interview), route('internships.qr')] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }

    $otherUser = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    $this->actingAs($otherUser)->get(route('attendance.qr', $event))->assertForbidden();
    $this->get(route('interviews.qr', $interview))->assertForbidden();
    $this->get(route('internships.qr'))->assertForbidden();
});

test('each event has its own QR and query parameters cannot replace its destination', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $event = shareAttendance($owner);
    $otherEvent = shareAttendance($owner);
    $this->actingAs($owner);

    $first = $this->get(route('attendance.qr', $event))->assertOk();
    $second = $this->get(route('attendance.qr', $otherEvent))->assertOk();
    $injected = $this->get(route('attendance.qr', ['attendance' => $event, 'url' => 'https://unrelated.example']))->assertOk();

    expect($second->getContent())->not->toBe($first->getContent());
    expect($injected->getContent())->toBe($first->getContent());
});
