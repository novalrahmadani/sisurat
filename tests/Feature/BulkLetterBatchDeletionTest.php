<?php

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('selected batches and their letters leave the archive while unselected records and number reservations remain', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $batches = LetterBatch::factory()->count(3)->for($owner)->create(['letter_date' => '2026-10-28']);
    $letters = $batches->map(fn (LetterBatch $batch, int $index): Letter => Letter::factory()->for($batch, 'batch')->create(['sequence_number' => $index + 101]));
    $other = LetterBatch::factory()->create();

    $response = $this->actingAs($owner)->delete(route('letters.batches.destroy-selected'), ['batch_ids' => [$batches[0]->id, $batches[2]->id]]);

    $response->assertRedirect(route('letters.index', ['page' => 1]));
    foreach ([0, 2] as $index) {
        $this->assertSoftDeleted($batches[$index]);
        $this->assertSoftDeleted($letters[$index]);
    }
    $this->assertNotSoftDeleted($batches[1]);
    $this->assertNotSoftDeleted($letters[1]);
    $this->assertNotSoftDeleted($other);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(104);
});

test('a mixed selection containing another owners batch deletes nothing', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $owned = LetterBatch::factory()->for($owner)->create();
    $other = LetterBatch::factory()->create();
    $ownedLetter = Letter::factory()->for($owned, 'batch')->create();
    $otherLetter = Letter::factory()->for($other, 'batch')->create();

    $response = $this->actingAs($owner)->delete(route('letters.batches.destroy-selected'), ['batch_ids' => [$owned->id, $other->id]]);

    $response->assertForbidden();
    foreach ([$owned, $other, $ownedLetter, $otherLetter] as $model) {
        $this->assertNotSoftDeleted($model);
    }
});

test('guests cannot delete selected batches', function () {
    $batch = LetterBatch::factory()->create();

    $response = $this->delete(route('letters.batches.destroy-selected'), ['batch_ids' => [$batch->id]]);

    $response->assertRedirect(route('login'));
    $this->assertNotSoftDeleted($batch);
});

test('invalid selections give a clear error and leave the archive unchanged', function (mixed $selection, string $field, string $message) {
    $owner = User::factory()->create(['is_active' => true]);
    $batch = LetterBatch::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->delete(route('letters.batches.destroy-selected'), ['batch_ids' => $selection]);

    $response->assertSessionHasErrors([$field => $message]);
    $this->assertNotSoftDeleted($batch);
})->with([
    'empty' => [[], 'batch_ids', 'Pilih setidaknya satu batch untuk dihapus.'],
    'missing' => [null, 'batch_ids', 'Pilih setidaknya satu batch untuk dihapus.'],
    'not an array' => ['all', 'batch_ids', 'Pilihan batch tidak valid.'],
    'too many' => [range(1, 101), 'batch_ids', 'Pilih maksimal 100 batch dalam satu penghapusan.'],
    'duplicate' => [[1, 1], 'batch_ids.0', 'Batch yang sama tidak boleh dipilih lebih dari sekali.'],
    'invalid identifier' => [['all'], 'batch_ids.0', 'Pilihan batch tidak valid.'],
    'nonpositive identifier' => [[0], 'batch_ids.0', 'Pilihan batch tidak valid.'],
]);

test('a stale or missing batch prevents partial deletion', function (bool $alreadyDeleted) {
    $owner = User::factory()->create(['is_active' => true]);
    $batch = LetterBatch::factory()->for($owner)->create();
    $letter = Letter::factory()->for($batch, 'batch')->create();
    $unavailableId = $batch->id + 100;
    if ($alreadyDeleted) {
        $removed = LetterBatch::factory()->for($owner)->create();
        $removed->delete();
        $unavailableId = $removed->id;
    }

    $response = $this->actingAs($owner)->delete(route('letters.batches.destroy-selected'), ['batch_ids' => [$batch->id, $unavailableId]]);

    $response->assertSessionHasErrors(['batch_ids' => 'Sebagian batch sudah tidak tersedia. Muat ulang arsip dan pilih kembali.']);
    $this->assertNotSoftDeleted($batch);
    $this->assertNotSoftDeleted($letter);
})->with([false, true]);

test('deleting the last page preserves filters and returns to an available page', function () {
    $owner = User::factory()->create(['is_active' => true]);
    LetterBatch::factory()->count(20)->for($owner)->create(['type' => 'permission', 'subject' => 'Sosialisasi']);
    $lastBatch = LetterBatch::factory()->for($owner)->create(['type' => 'permission', 'subject' => 'Sosialisasi']);

    $response = $this->actingAs($owner)->delete(route('letters.batches.destroy-selected', ['q' => 'Sosialisasi', 'type' => 'permission', 'page' => 2]), ['batch_ids' => [$lastBatch->id]]);

    $response->assertRedirect(route('letters.index', ['q' => 'Sosialisasi', 'type' => 'permission', 'page' => 1]));
    $this->assertSoftDeleted($lastBatch);
    expect(LetterBatch::query()->count())->toBe(20);
});
