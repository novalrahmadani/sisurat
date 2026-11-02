<?php

use App\Models\District;
use App\Models\LetterBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('socialization and assignment forms suggest the revised UNUHA letter code', function (string $routeName) {
    $owner = User::factory()->create(['is_active' => true]);

    $this->actingAs($owner)->get(route($routeName))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('letters/Create')
            ->where('defaults.numberPrefix', 'UNUHA.11/TU.2.1')
            ->where('defaults.pkkmbNumberPrefix', 'PPMB-UNUHA/TU.1.1'));
})->with(['letters.permission.school', 'letters.permission.pesantren', 'letters.assignment.school', 'letters.assignment.pesantren']);

test('the revised code is retained in the saved letter number for both letter types', function (string $type) {
    $owner = User::factory()->create(['is_active' => true]);
    $district = District::query()->create(['name' => 'Kabupaten Contoh', 'identity_key' => District::identityKey('Kabupaten Contoh')]);
    $item = ['institution_type' => 'school', 'institution_name' => 'SMA Contoh',
        'recipient_position' => 'Kepala Sekolah', 'recipient_district' => $district->name,
        'assignees' => [['name' => 'Petugas Contoh', 'office' => 'Dosen']]];

    $response = $this->actingAs($owner)->post(route('letters.store'), [
        'type' => $type, 'letter_date' => '2026-10-29', 'first_sequence' => 455,
        'number_prefix' => 'UNUHA.11/TU.2.1', 'signatory_title' => 'Kepala Pusat PMB',
        'signatory_name' => 'Penandatangan Contoh', 'include_signature' => false, 'include_stamp' => false,
        'event_start' => '2026-10-29', 'event_end' => '2026-10-30', 'items' => [$item],
    ])->assertSessionHasNoErrors();

    $response->assertRedirect(route('letters.batch', LetterBatch::query()->sole()));

    $this->assertDatabaseHas('letter_batches', ['type' => $type, 'number_prefix' => 'UNUHA.11/TU.2.1', 'user_id' => $owner->id]);
    $this->assertDatabaseHas('letters', ['letter_number' => '455/UNUHA.11/TU.2.1/X/2026']);
})->with(['permission', 'assignment']);
