<?php

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\ManualOutgoingLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

function financeAgendaPayload(): array
{
    return ['recipient' => 'REKTOR', 'letter_date' => '2026-10-30', 'subject' => 'SURAT KEUANGAN PMB',
        'letter_number' => '466/UNUHA.11/KU/X/2026', 'type' => 'finance'];
}

test('a finance record preserves its distinct code while reserving the shared PMB sequence', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $this->actingAs($owner)->post(route('outgoing-letters.store'), financeAgendaPayload())->assertSessionHasNoErrors()->assertRedirect(route('outgoing-letters.index', ['year' => 2026]));
    $this->assertDatabaseHas('manual_outgoing_letters', ['type' => 'finance', 'letter_number' => '466/UNUHA.11/KU/X/2026', 'sequence_number' => 466, 'letter_year' => 2026]);
    expect(Letter::nextSequencesByYear()['2026'])->toBe(467);
    $this->get(route('letters.permission.school'))->assertInertia(fn (Assert $page) => $page->where('nextSequence', 467));
    $this->post(route('outgoing-letters.store'), [...financeAgendaPayload(), 'type' => 'general', 'letter_number' => '466/UNUHA.11/TU.2.1/X/2026'])->assertSessionHasErrors('letter_number');
    $this->assertDatabaseCount('manual_outgoing_letters', 1);
});

test('finance records cannot reuse an official PMB number even when the code differs', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $batch = LetterBatch::factory()->create(['user_id' => $owner->id, 'letter_date' => '2026-10-30', 'is_trial' => false]);
    Letter::factory()->create(['letter_batch_id' => $batch->id, 'sequence_number' => 466, 'numbering_scope' => 'official', 'letter_number' => '466/UNUHA.11/TU.2.1/X/2026']);
    $this->actingAs($owner)->post(route('outgoing-letters.store'), financeAgendaPayload())->assertSessionHasErrors('letter_number');
    $this->assertDatabaseCount('manual_outgoing_letters', 0);
});

test('finance categories appear in creation filters CSV and PDF without requiring a file', function () {
    $owner = User::factory()->create(['is_active' => true]);
    ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'letter_year' => 2026, 'letter_date' => '2026-10-30', 'sequence_number' => 1, 'letter_number' => '001/UNUHA.11/KU/X/2026', 'recipient' => 'Rektor Keuangan', 'subject' => 'Keuangan PMB', 'type' => 'finance']);
    ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'letter_year' => 2026, 'letter_date' => '2026-10-30', 'sequence_number' => 2, 'letter_number' => '002/UNUHA.11/TU.2.1/X/2026', 'subject' => 'Undangan rapat', 'type' => 'general']);
    $this->actingAs($owner)->get(route('outgoing-letters.create'))->assertInertia(fn (Assert $page) => $page->where('types.finance', 'Surat Keuangan'));
    $this->get(route('outgoing-letters.index', ['type' => 'finance', 'year' => 2026]))->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.type', 'finance')->where('records.data.0.letter_number', '001/UNUHA.11/KU/X/2026'));
    $csv = $this->get(route('outgoing-letters.export', ['type' => 'finance', 'year' => 2026]))->assertOk();
    expect($csv->streamedContent())->toContain('Rektor Keuangan', '001/UNUHA.11/KU/X/2026')->not->toContain('Undangan rapat');
    $pdf = $this->get(route('outgoing-letters.pdf', ['type' => 'finance', 'year' => 2026]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
});

test('editing an existing manual record can classify it as finance without changing its number', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $entry = ManualOutgoingLetter::factory()->create(['created_by' => $owner->id, 'letter_year' => 2026, 'letter_date' => '2026-10-30', 'sequence_number' => 466, 'letter_number' => '466/UNUHA.11/KU/X/2026', 'type' => 'general']);
    $this->actingAs($owner)->put(route('outgoing-letters.update', $entry), [...financeAgendaPayload(), 'expected_version' => $entry->version])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('manual_outgoing_letters', ['id' => $entry->id, 'type' => 'finance', 'letter_number' => '466/UNUHA.11/KU/X/2026']);
    $this->assertDatabaseCount('manual_outgoing_letters', 1);
});
