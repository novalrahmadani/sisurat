<?php

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function numberingAdmin(): User
{
    return User::factory()->create(['is_active' => true, 'role' => User::ROLE_ADMIN]);
}

function numberedLetterPayload(int $start = 455, string $date = '2026-10-28'): array
{
    return ['type' => 'permission', 'letter_date' => $date, 'first_sequence' => $start,
        'number_prefix' => 'PPMBPM-UNUHA/TU.2.1', 'signatory_title' => 'Ketua PMB', 'signatory_name' => 'Wardianto, M.Kom',
        'items' => [['institution_type' => 'school', 'institution_name' => 'SMA Contoh', 'recipient_position' => 'Kepala']]];
}

test('admin can leave the official starting number unset until ready', function () {
    $this->actingAs(numberingAdmin())->get(route('letter-numbering.edit', ['year' => 2026]))
        ->assertInertia(fn (Assert $page) => $page->component('settings/LetterNumbering')->where('year', 2026)->where('configured', false)->where('nextSequence', 1));
    $this->assertDatabaseCount('letter_numberings', 0);
});

test('admin sets a yearly starting number which is suggested on both letter forms', function () {
    $admin = numberingAdmin();
    $this->actingAs($admin)->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 500, 'expected_next_sequence' => 1])->assertSessionHasNoErrors()->assertRedirect(route('letter-numbering.edit', ['year' => 2026]));

    $this->assertDatabaseHas('letter_numberings', ['year' => 2026, 'next_sequence' => 500, 'updated_by' => $admin->id]);
    foreach (['letters.permission.school', 'letters.general'] as $route) {
        $this->get(route($route))->assertInertia(fn (Assert $page) => $page->where('nextSequencesByYear.2026', 500));
    }
    expect(Letter::nextSequencesByYear())->not->toHaveKey('2027');
});

test('trial archive preserves all old records and allows an official letter to reuse a trial number', function () {
    $admin = numberingAdmin();
    $trial = LetterBatch::factory()->for($admin)->create(['letter_date' => '2026-10-28']);
    $old = Letter::factory()->for($trial, 'batch')->create(['sequence_number' => 455, 'letter_number' => '455/PPMBPM-UNUHA/TU.2.1/X/2026']);
    $deleted = Letter::factory()->for($trial, 'batch')->create(['sequence_number' => 2539]);
    $deleted->delete();

    $this->artisan('letters:archive-trial', ['--year' => 2026, '--through-batch' => $trial->id, '--apply' => true])->assertSuccessful();
    $this->actingAs($admin)->post(route('letters.store'), numberedLetterPayload())->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('letter_batches', ['id' => $trial->id, 'is_trial' => true]);
    $this->assertDatabaseHas('letters', ['id' => $old->id, 'letter_number' => $old->letter_number, 'numbering_scope' => 'trial-'.$trial->id]);
    $this->assertSoftDeleted($deleted);
    $this->assertDatabaseHas('letters', ['letter_number' => $old->letter_number, 'numbering_scope' => 'official']);
    $this->assertDatabaseCount('letters', 3);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(456);
});

test('permission and general letters share the same advancing official sequence', function () {
    $admin = numberingAdmin();
    $this->actingAs($admin)->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 455, 'expected_next_sequence' => 1])->assertSessionHasNoErrors();
    $this->post(route('letters.store'), numberedLetterPayload())->assertSessionHasNoErrors();
    $payload = array_replace(numberedLetterPayload(456), ['subject' => 'Undangan rapat', 'body' => 'Mengundang rapat.', 'items' => [['recipient_name' => 'Peserta rapat']]]);

    $this->post(route('letters.general.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    expect(Letter::nextSequencesByYear()['2026'])->toBe(457);
    $this->post(route('letters.store'), numberedLetterPayload(456))->assertSessionHasErrors('first_sequence');
    $this->assertDatabaseCount('letters', 2);
});

test('used official numbers remain reserved after a batch is deleted', function () {
    $admin = numberingAdmin();
    $this->actingAs($admin)->post(route('letters.store'), numberedLetterPayload())->assertSessionHasNoErrors();
    $batch = LetterBatch::query()->sole();
    $this->delete(route('letters.batches.destroy', $batch))->assertRedirect();

    $this->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 455, 'expected_next_sequence' => 456])->assertSessionHasErrors(['next_sequence' => 'Nomor surat resmi tahun 2026 sudah digunakan atau dicadangkan sampai 455. Gunakan minimal 456.']);

    expect(Letter::nextSequencesByYear()['2026'])->toBe(456);
});

test('a new year starts from one and keeps prior year settings', function () {
    $admin = numberingAdmin();
    $this->actingAs($admin)->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 900, 'expected_next_sequence' => 1])->assertSessionHasNoErrors();

    $this->post(route('letters.store'), numberedLetterPayload(1, '2027-01-01'))->assertSessionHasNoErrors();

    expect(Letter::nextSequencesByYear())->toMatchArray(['2026' => 900, '2027' => 2]);
});

test('stale settings cannot overwrite a newer number', function () {
    $admin = numberingAdmin();
    LetterNumbering::query()->create(['year' => 2026, 'next_sequence' => 500]);

    $this->actingAs($admin)->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => 455, 'expected_next_sequence' => 1])->assertSessionHasErrors(['next_sequence' => 'Penomoran telah berubah. Muat ulang halaman dan periksa nomor terbaru.']);

    $this->assertDatabaseHas('letter_numberings', ['year' => 2026, 'next_sequence' => 500]);
});

test('staff and guests cannot manage official numbering', function (bool $guest) {
    if (! $guest) {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => User::ROLE_STAFF]));
    }
    foreach (['get', 'put'] as $method) {
        $response = $this->{$method}(route($method === 'get' ? 'letter-numbering.edit' : 'letter-numbering.update'), ['year' => 2026, 'next_sequence' => 455, 'expected_next_sequence' => 1]);
        $guest ? $response->assertRedirect(route('login')) : $response->assertForbidden();
    }
    $this->assertDatabaseCount('letter_numberings', 0);
})->with([true, false]);

test('invalid starting numbers leave settings unchanged', function (mixed $number, string $message) {
    $this->actingAs(numberingAdmin())->put(route('letter-numbering.update'), ['year' => 2026, 'next_sequence' => $number, 'expected_next_sequence' => 1])->assertSessionHasErrors(['next_sequence' => $message]);
    $this->assertDatabaseCount('letter_numberings', 0);
})->with([
    [null, 'Isi nomor surat berikutnya setelah nomor terakhir pada buku agenda.'],
    [0, 'Nomor surat harus antara 1 dan 99999.'],
    [100000, 'Nomor surat harus antara 1 dan 99999.'],
    ['abc', 'Nomor surat harus berupa bilangan bulat.'],
]);

test('trial archive is bounded by the confirmed batch and year and is safe to repeat', function () {
    $trial = LetterBatch::factory()->create(['letter_date' => '2026-01-01']);
    $old = Letter::factory()->for($trial, 'batch')->create(['sequence_number' => 2539]);
    $otherYear = LetterBatch::factory()->create(['letter_date' => '2025-01-01']);
    $futureOfficial = LetterBatch::factory()->create(['letter_date' => '2026-11-01']);
    $new = Letter::factory()->for($futureOfficial, 'batch')->create(['sequence_number' => 455]);

    $this->artisan('letters:archive-trial', ['--year' => 2026, '--through-batch' => $trial->id])->assertSuccessful();
    $this->assertDatabaseCount('letter_numberings', 0);
    $this->assertDatabaseHas('letter_batches', ['id' => $trial->id, 'is_trial' => false]);
    foreach ([1, 2] as $attempt) {
        $this->artisan('letters:archive-trial', ['--year' => 2026, '--through-batch' => $trial->id, '--apply' => true])->assertSuccessful();
    }

    $this->assertDatabaseHas('letter_batches', ['id' => $otherYear->id, 'is_trial' => false]);
    $this->assertDatabaseHas('letters', ['id' => $new->id, 'numbering_scope' => 'official']);
    $this->assertDatabaseHas('letters', ['id' => $old->id, 'numbering_scope' => 'trial-'.$trial->id]);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(456);
});
