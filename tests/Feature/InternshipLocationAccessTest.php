<?php

use App\Models\InternshipAttendance;
use App\Models\InternshipParticipant;
use App\Models\InternshipSetting;
use App\Models\User;
use App\Support\InternshipDeviceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-29 08:15:00', 'Asia/Jakarta'));
});

function gpsParticipant(): InternshipParticipant
{
    return InternshipParticipant::factory()->create(['device_token_hash' => hash('sha256', str_repeat('g', 64)), 'device_expires_at' => now()->addDays(180)]);
}

function gpsAttendanceData(array $changes = []): array
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    $pixels = '';
    for ($y = 0; $y < 32; $y++) {
        $pixels .= "\0";
        for ($x = 0; $x < 64; $x++) {
            $pixels .= abs($x - $y * 2) < 4 ? "\x10\x10\x10" : "\xff\xff\xff";
        }
    }
    $signature = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 64, 32, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($pixels)).$chunk('IEND', ''));

    return [...['action' => 'check_in', 'signature' => $signature, 'latitude' => -4.123, 'longitude' => 104.123, 'accuracy' => 10, 'position_captured_at' => now()->timestamp], ...$changes];
}

test('location at the configured PMB point permits attendance without storing participant coordinates', function () {
    InternshipSetting::factory()->create(['id' => 1]);
    $participant = gpsParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData())->assertSessionHasNoErrors();
    $this->assertDatabaseHas('internship_attendances', ['internship_participant_id' => $participant->id, 'checked_in_at' => '2026-10-29 01:15:00']);
    expect(InternshipAttendance::firstOrFail()->getAttributes())->not->toHaveKeys(['latitude', 'longitude']);
});

test('fresh location inside the radius is accepted and a point outside it is rejected', function (float $latitude, bool $allowed) {
    InternshipSetting::factory()->create(['id' => 1, 'radius_meters' => 100]);
    $participant = gpsParticipant();
    $response = $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData(['latitude' => $latitude]));
    if ($allowed) {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('internship_attendances', 1);
    } else {
        $response->assertSessionHasErrors('location');
        $this->assertDatabaseCount('internship_attendances', 0);
    }
})->with(['around 56 meters' => [-4.1225, true], 'around 111 meters' => [-4.122, false]]);

test('checkout checks location again and cannot be completed from outside PMB', function () {
    InternshipSetting::factory()->create(['id' => 1]);
    $participant = gpsParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData())->assertSessionHasNoErrors();
    $this->travel(8)->hours();
    $this->post(route('internship.submit'), gpsAttendanceData(['action' => 'check_out', 'latitude' => -4.1, 'location_verified' => true, 'distance' => 0]))
        ->assertSessionHasErrors('location');
    expect(InternshipAttendance::firstOrFail()->checked_out_at)->toBeNull();
    $this->post(route('internship.submit'), gpsAttendanceData(['action' => 'check_out']))->assertSessionHasNoErrors();
    expect(InternshipAttendance::firstOrFail()->checked_out_at->format('H:i:s'))->toBe('09:15:00');
});

test('unconfigured location does not silently permit attendance', function () {
    $participant = gpsParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData())->assertSessionHasErrors(['location' => 'Titik lokasi kantor PMB belum ditetapkan. Hubungi admin PMB sebelum melakukan absensi.']);
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('invalid missing inaccurate or stale GPS data cannot be saved', function (array $changes, string $field) {
    InternshipSetting::factory()->create(['id' => 1]);
    $participant = gpsParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData($changes))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([
    [['latitude' => null], 'latitude'], [['latitude' => 91], 'latitude'], [['longitude' => 181], 'longitude'],
    [['latitude' => [1]], 'latitude'], [['accuracy' => 101], 'accuracy'], [['accuracy' => 0], 'accuracy'],
    [['position_captured_at' => 1], 'location'], [['position_captured_at' => null], 'position_captured_at'],
]);

test('GPS accuracy must also fit within a small configured radius', function () {
    InternshipSetting::factory()->create(['id' => 1, 'radius_meters' => 20]);
    $participant = gpsParticipant();
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post(route('internship.submit'), gpsAttendanceData(['accuracy' => 30]))->assertSessionHasErrors('location');
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('a phone using insecure HTTP cannot bypass the HTTPS requirement by posting coordinates', function () {
    InternshipSetting::factory()->create(['id' => 1]);
    $participant = gpsParticipant();
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.42'])->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('g', 64))
        ->post('http://192.168.1.72:8000/absensi-magang', gpsAttendanceData())->assertSessionHasErrors('location');
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('admin can set PMB coordinates and radius and unexpected settings are ignored', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($admin)->put(route('internships.access.update'), ['latitude' => -4.123, 'longitude' => 104.123, 'radius_meters' => 75, 'access_mode' => 'open'])
        ->assertSessionHasNoErrors()->assertRedirect(route('internships.access.edit'));
    $this->assertDatabaseHas('internship_settings', ['id' => 1, 'radius_meters' => 75]);
});

test('invalid PMB coordinates and radius do not replace existing settings', function (array $changes, string $field) {
    InternshipSetting::factory()->create(['id' => 1]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $this->actingAs($admin)->put(route('internships.access.update'), [...['latitude' => -4.123, 'longitude' => 104.123, 'radius_meters' => 100], ...$changes])->assertSessionHasErrors($field);
    expect(InternshipSetting::current()->radius_meters)->toBe(100);
})->with([[['latitude' => -91], 'latitude'], [['longitude' => 181], 'longitude'], [['radius_meters' => 19], 'radius_meters'], [['radius_meters' => 1001], 'radius_meters']]);

test('visitors and staff cannot change the PMB location restriction', function () {
    $this->get(route('internships.access.edit'))->assertRedirect(route('login'));
    $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    $this->actingAs($staff)->get(route('internships.access.edit'))->assertForbidden();
    $this->put(route('internships.access.update'), ['latitude' => 0, 'longitude' => 0, 'radius_meters' => 100])->assertForbidden();
    $this->assertDatabaseCount('internship_settings', 0);
});
