<?php

use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function letterBatchPayload(string $type, array $items): array
{
    return [
        'type' => $type,
        'letter_date' => '2026-10-06',
        'first_sequence' => 1,
        'number_prefix' => 'PPMBPM-UNUHA/TU.2.1',
        'signatory_title' => 'Ketua Pusat PMB',
        'signatory_name' => 'Wardianto, M.Kom',
        'signatory_number' => 'NIP.Y 00000000000000.0.000',
        'event_start' => '2026-10-07',
        'event_end' => '2026-10-09',
        'event_location' => 'Kampus Universitas Nurul Huda',
        'academic_year' => '2026/2027',
        'items' => $items,
    ];
}

function pkkmbParticipant(string $status = 'attended'): array
{
    return [
        'participant_name' => 'Siti Aminah',
        'participant_nim' => '00123456',
        'participant_study_program' => 'Pendidikan Bahasa Inggris',
        'participation_status' => $status,
    ];
}

test('guests cannot create a batch of letters', function () {
    $response = $this->post(route('letters.store'), letterBatchPayload('pkkmb', [pkkmbParticipant()]));

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
});

test('permission letters preserve the selected institution and recipient', function (string $institutionType, string $institutionName, string $position) {
    $user = User::factory()->create();
    $payload = letterBatchPayload('permission', [[
        'institution_type' => $institutionType,
        'institution_name' => $institutionName,
        'recipient_position' => $position,
        'recipient_name' => 'Ahmad',
        'recipient_district' => 'Kabupaten Ogan Komering Ulu',
        'target_class' => 'Kelas XII',
    ]]);

    $response = $this->actingAs($user)->post(route('letters.store'), $payload);

    $response->assertSessionHasNoErrors()->assertRedirect(route('letters.index'));
    $this->assertDatabaseHas('letter_batches', ['type' => 'permission', 'user_id' => $user->id, 'event_location' => null, 'academic_year' => null]);
    $this->assertDatabaseHas('letters', [
        'letter_number' => '001/PPMBPM-UNUHA/TU.2.1/X/2026',
        'institution_type' => $institutionType,
        'institution_name' => $institutionName,
        'recipient_position' => $position,
        'recipient_name' => 'Ahmad',
        'recipient_district' => 'Kabupaten Ogan Komering Ulu',
        'target_class' => 'Kelas XII',
        'participant_name' => null,
    ]);
    $this->assertDatabaseCount('letter_assignees', 0);
})->with([
    'school' => ['school', 'MAN 1 Ogan Komering Ulu', 'Kepala'],
    'pesantren' => ['pesantren', 'Pondok Pesantren Thawalib Darussalam', 'Pimpinan Pondok Pesantren'],
]);

test('assignment letters preserve the destination and ordered assignees', function (string $institutionType, string $destination) {
    $user = User::factory()->create();
    $payload = letterBatchPayload('assignment', [[
        'institution_type' => $institutionType,
        'institution_name' => 'Tujuan sosialisasi',
        'recipient_district' => 'Kabupaten Ogan Komering Ilir',
        'activity_destination' => $destination,
        'assignees' => [
            ['name' => 'Fitria Apriani, M.Kom', 'office' => 'Dosen', 'district' => 'OKI'],
            ['name' => 'Nirma, M.Ti', 'office' => 'Dosen', 'district' => 'OKI'],
        ],
    ]]);

    $response = $this->actingAs($user)->post(route('letters.store'), $payload);

    $response->assertSessionHasNoErrors()->assertRedirect(route('letters.index'));
    $this->assertDatabaseHas('letter_batches', ['type' => 'assignment', 'user_id' => $user->id]);
    $batch = LetterBatch::query()->sole();
    expect($batch->event_start->toDateString())->toBe('2026-10-07');
    expect($batch->event_end->toDateString())->toBe('2026-10-09');
    $this->assertDatabaseHas('letters', [
        'institution_type' => $institutionType,
        'institution_name' => 'Tujuan sosialisasi',
        'recipient_district' => 'Kabupaten Ogan Komering Ilir',
        'activity_destination' => $destination,
        'participant_name' => null,
    ]);
    $this->assertDatabaseCount('letter_assignees', 2);
    $this->assertDatabaseHas('letter_assignees', ['name' => 'Fitria Apriani, M.Kom', 'sort_order' => 0]);
    $this->assertDatabaseHas('letter_assignees', ['name' => 'Nirma, M.Ti', 'sort_order' => 1]);
})->with([
    'school' => ['school', 'sekolah-sekolah tingkat SMA, SMK, dan MA'],
    'pesantren' => ['pesantren', 'pondok-pondok pesantren'],
]);

test('PKKMB letters preserve participant status and the leading zeroes of NIM', function (string $status) {
    $user = User::factory()->create();
    $participant = pkkmbParticipant($status) + [
        'institution_type' => 'school',
        'institution_name' => 'Data tujuan yang tidak digunakan',
        'activity_destination' => 'Data tugas yang tidak digunakan',
        'assignees' => [['name' => 'Data petugas', 'office' => 'Dosen', 'district' => 'OKI']],
    ];

    $response = $this->actingAs($user)->post(route('letters.store'), letterBatchPayload('pkkmb', [$participant]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('letters.index'));
    $this->assertDatabaseHas('letter_batches', [
        'type' => 'pkkmb',
        'user_id' => $user->id,
        'event_location' => 'Kampus Universitas Nurul Huda',
        'academic_year' => '2026/2027',
    ]);
    $batch = LetterBatch::query()->sole();
    expect($batch->event_start->toDateString())->toBe('2026-10-07');
    expect($batch->event_end->toDateString())->toBe('2026-10-09');
    $this->assertDatabaseHas('letters', [
        'participant_name' => 'Siti Aminah',
        'participant_nim' => '00123456',
        'participant_study_program' => 'Pendidikan Bahasa Inggris',
        'participation_status' => $status,
        'institution_type' => null,
        'institution_name' => null,
        'activity_destination' => null,
    ]);
    $this->assertDatabaseCount('letter_assignees', 0);
})->with(['attended', 'completed', 'registered']);

test('PKKMB requires event details and participant identity before saving', function () {
    $user = User::factory()->create();
    $payload = letterBatchPayload('pkkmb', [[]]);
    unset($payload['event_start'], $payload['event_end'], $payload['event_location'], $payload['academic_year']);

    $response = $this->actingAs($user)->post(route('letters.store'), $payload);

    $response->assertSessionHasErrors([
        'event_start' => 'Tanggal mulai kegiatan wajib diisi.',
        'event_end' => 'Tanggal selesai kegiatan wajib diisi.',
        'event_location' => 'Lokasi kegiatan PKKMB wajib diisi.',
        'academic_year' => 'Tahun akademik PKKMB wajib diisi.',
        'items.0.participant_name' => 'Nama peserta PKKMB wajib diisi.',
        'items.0.participant_nim' => 'NIM peserta PKKMB wajib diisi.',
        'items.0.participant_study_program' => 'Program studi peserta PKKMB wajib diisi.',
        'items.0.participation_status' => 'Pilih status keikutsertaan peserta PKKMB.',
    ]);
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
});

test('PKKMB rejects invalid participant values without saving a batch', function (string $field, mixed $value, string $message) {
    $user = User::factory()->create();
    $participant = pkkmbParticipant();
    $participant[$field] = $value;

    $response = $this->actingAs($user)->post(route('letters.store'), letterBatchPayload('pkkmb', [$participant]));

    $response->assertSessionHasErrors(['items.0.'.$field => $message]);
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
})->with([
    'unknown status' => ['participation_status', 'passed', 'Status PKKMB harus terdaftar, telah mengikuti, atau telah menyelesaikan.'],
    'numeric NIM' => ['participant_nim', 123456, 'NIM peserta PKKMB harus berupa teks.'],
    'NIM too long' => ['participant_nim', str_repeat('1', 51), 'NIM peserta PKKMB maksimal 50 karakter.'],
    'name too long' => ['participant_name', str_repeat('A', 151), 'Nama peserta PKKMB maksimal 150 karakter.'],
    'program too long' => ['participant_study_program', str_repeat('A', 151), 'Program studi peserta PKKMB maksimal 150 karakter.'],
]);

test('PKKMB rejects invalid event details without saving a batch', function (string $field, mixed $value, string $message) {
    $user = User::factory()->create();
    $payload = letterBatchPayload('pkkmb', [pkkmbParticipant()]);
    $payload[$field] = $value;

    $response = $this->actingAs($user)->post(route('letters.store'), $payload);

    $response->assertSessionHasErrors([$field => $message]);
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
})->with([
    'invalid start date' => ['event_start', 'bukan tanggal', 'Tanggal mulai kegiatan tidak valid.'],
    'invalid end date' => ['event_end', 'bukan tanggal', 'Tanggal selesai kegiatan tidak valid.'],
    'reversed dates' => ['event_end', '2026-10-06', 'Tanggal selesai harus sama dengan atau setelah tanggal mulai kegiatan.'],
    'location too long' => ['event_location', str_repeat('A', 256), 'Lokasi kegiatan PKKMB maksimal 255 karakter.'],
    'academic year too long' => ['academic_year', str_repeat('A', 31), 'Tahun akademik PKKMB maksimal 30 karakter.'],
]);

test('school and pesantren are the only supported permission and assignment targets', function (string $type, ?string $target, string $message) {
    $user = User::factory()->create();
    $payload = letterBatchPayload($type, [[
        'institution_type' => $target,
        'institution_name' => 'Tujuan surat',
        'recipient_position' => 'Kepala',
        'activity_destination' => 'Tujuan sosialisasi',
        'assignees' => [['name' => 'Petugas', 'office' => 'Dosen', 'district' => 'OKI']],
    ]]);

    $response = $this->actingAs($user)->post(route('letters.store'), $payload);

    $response->assertSessionHasErrors(['items.0.institution_type' => $message]);
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
})->with([
    'permission unknown target' => ['permission', 'university', 'Tujuan surat harus sekolah atau pondok pesantren.'],
    'assignment unknown target' => ['assignment', 'university', 'Tujuan surat harus sekolah atau pondok pesantren.'],
    'permission missing target' => ['permission', null, 'Pilih tujuan surat: sekolah atau pondok pesantren.'],
    'assignment missing target' => ['assignment', null, 'Pilih tujuan surat: sekolah atau pondok pesantren.'],
]);

test('unsupported letter types are rejected without creating letters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('letters.store'), letterBatchPayload('other', [pkkmbParticipant()]));

    $response->assertSessionHasErrors(['type' => 'Pilih jenis surat yang tersedia.']);
    $this->assertDatabaseCount('letter_batches', 0);
    $this->assertDatabaseCount('letters', 0);
});

test('51 PKKMB letters are split into batches of 50 and 1 with continuous numbers', function () {
    $user = User::factory()->create();
    $participants = array_map(fn (int $index) => array_replace(pkkmbParticipant(), ['participant_name' => 'Peserta '.$index]), range(1, 51));

    $response = $this->actingAs($user)->post(route('letters.store'), letterBatchPayload('pkkmb', $participants));

    $response->assertSessionHasNoErrors()->assertRedirect(route('letters.index'));
    $batches = LetterBatch::query()->withCount('letters')->orderBy('first_sequence')->get();
    expect($batches->pluck('letters_count')->all())->toBe([50, 1]);
    expect($batches->pluck('first_sequence')->all())->toBe([1, 51]);
    $this->assertDatabaseCount('letters', 51);
    $this->assertDatabaseHas('letters', ['sequence_number' => 50, 'letter_number' => '050/PPMBPM-UNUHA/TU.2.1/X/2026']);
    $this->assertDatabaseHas('letters', ['sequence_number' => 51, 'letter_number' => '051/PPMBPM-UNUHA/TU.2.1/X/2026']);
    $this->assertDatabaseCount('letter_assignees', 0);
});

test('legacy assignment batches without an institution type remain viewable', function () {
    $user = User::factory()->create();
    $batch = LetterBatch::factory()->assignment()->for($user)->create();
    Letter::factory()->legacyAssignment()->for($batch, 'batch')->create();

    $response = $this->actingAs($user)->get(route('letters.batch', $batch));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('letters/Batch')
        ->where('batch.type', 'assignment')
        ->has('batch.letters', 1)
        ->where('batch.letters.0.institution_type', null)
        ->where('batch.letters.0.activity_destination', 'sekolah-sekolah tingkat SMA, SMK, dan MA')
    );
});
