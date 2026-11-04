<?php

use App\Models\InternshipParticipant;
use App\Models\User;
use App\Support\InternshipDeviceAccess;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('lets an admin delete a participant without attendance records', function () {
    $participant = InternshipParticipant::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->delete(route('internships.destroy', $participant))
        ->assertRedirect(route('internships.index', ['q' => '', 'month' => now('Asia/Jakarta')->format('Y-m'), 'tab' => 'participants']));

    $this->assertModelMissing($participant);
    $this->assertAuthenticatedAs($admin);
    $this->delete(route('internships.destroy', $participant->id))->assertNotFound();
});

it('deletes only the selected participant and their attendance and revokes their device access', function () {
    $participant = InternshipParticipant::factory()->create([
        'device_token_hash' => hash('sha256', str_repeat('d', 64)),
        'device_expires_at' => now()->addDays(180),
    ]);
    $attendance = $participant->attendances()->create([
        'attendance_date' => '2026-11-04',
        'checked_in_at' => '2026-11-04 01:00:00',
        'check_in_signature' => base64_encode('test signature'),
    ]);
    $otherParticipant = InternshipParticipant::factory()->create();
    $otherAttendance = $otherParticipant->attendances()->create([
        'attendance_date' => '2026-11-04',
        'checked_in_at' => '2026-11-04 01:00:00',
        'check_in_signature' => base64_encode('other test signature'),
    ]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $sessionProof = ['id' => (int) $participant->id, 'fingerprint' => $participant->device_token_hash];

    $this->actingAs($admin)->delete(route('internships.destroy', $participant))->assertRedirect();

    $this->assertModelMissing($participant);
    $this->assertModelMissing($attendance);
    $this->assertModelExists($otherParticipant);
    $this->assertModelExists($otherAttendance);
    $this->withCookie(InternshipDeviceAccess::COOKIE, $participant->id.':'.str_repeat('d', 64))
        ->withSession([InternshipDeviceAccess::SESSION => $sessionProof])
        ->get(route('internship.show'))
        ->assertInertia(fn (Assert $page) => $page->where('participant', null));
});

it('refuses participant deletion for visitors and staff', function () {
    $participant = InternshipParticipant::factory()->create();

    $this->delete(route('internships.destroy', $participant))->assertRedirect(route('login'));
    $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true]);
    $this->actingAs($staff)->delete(route('internships.destroy', $participant))->assertForbidden();

    $this->assertModelExists($participant);
});

it('shows the full attendance count for the delete confirmation', function () {
    $participant = InternshipParticipant::factory()->create();
    $participant->attendances()->create([
        'attendance_date' => '2026-10-01',
        'checked_in_at' => '2026-10-01 01:00:00',
        'check_in_signature' => base64_encode('test signature'),
    ]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->get(route('internships.index', ['tab' => 'participants', 'month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->where('participants.data.0.attendance_count', 1));
});
