<?php

use App\Models\AttendanceEvent;
use App\Models\LetterBatch;
use App\Models\PmbSignatory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

function pmbAdmin(): User
{
    return User::factory()->create(['is_active' => true, 'role' => User::ROLE_ADMIN]);
}
function pmbProfile(array $changes = []): array
{
    $current = PmbSignatory::current();

    return array_replace(['revision' => $current->id, 'name' => $current->name, 'title' => $current->title, 'number' => $current->number], $changes);
}
function pmbLetter(array $changes = []): array
{
    $current = PmbSignatory::current();

    return array_replace(['type' => 'permission', 'letter_date' => '2026-10-28', 'first_sequence' => 1, 'number_prefix' => 'PPMBPM-UNUHA/TU.2.1',
        'signatory_name' => $current->name, 'signatory_title' => $current->title, 'signatory_number' => $current->number,
        'items' => [['institution_type' => 'school', 'institution_name' => 'SMA Contoh', 'recipient_position' => 'Kepala']]], $changes);
}

test('admin can see and update the central signatory and both letter forms use the latest data', function () {
    $admin = pmbAdmin();
    $this->actingAs($admin)->get(route('master-data.pmb-signatory.edit'))->assertInertia(fn (Assert $page) => $page->component('master-data/PmbSignatory')->where('signatory.name', 'Wardianto, M.Kom'));

    $this->post(route('master-data.pmb-signatory.update'), pmbProfile(['title' => 'Kepala Pusat PMB', 'number' => 'NIP.Y BARU']))->assertSessionHasNoErrors()->assertRedirect(route('master-data.pmb-signatory.edit'));

    foreach (['letters.permission.school', 'letters.general'] as $route) {
        $this->get(route($route))->assertInertia(fn (Assert $page) => $page->where('defaults.signatoryTitle', 'Kepala Pusat PMB')->where('defaults.signatoryNumber', 'NIP.Y BARU'));
    }
    expect(PmbSignatory::legacy()->title)->toBe('Ketua Pusat PMB');
    expect(PmbSignatory::current()->signature_path)->toBe('images/pmb-signature.png');
});

test('changing the person clears the old signature while keeping the institutional stamp', function () {
    $this->actingAs(pmbAdmin())->post(route('master-data.pmb-signatory.update'), pmbProfile(['name' => 'Ketua Baru, M.Pd']))->assertSessionHasNoErrors();

    expect(PmbSignatory::current()->signature_path)->toBeNull();
    expect(PmbSignatory::current()->stamp_path)->toBe('images/pmb-stamp.png');
    $this->post(route('letters.store'), pmbLetter())->assertSessionHasNoErrors();
    $this->assertDatabaseHas('letter_batches', ['include_signature' => false, 'include_stamp' => true]);
});

test('uploaded images are private and old revisions retain their original files', function () {
    $this->actingAs(pmbAdmin())->post(route('master-data.pmb-signatory.update'), pmbProfile(['signature' => UploadedFile::fake()->image('ttd.png', 360, 140), 'stamp' => UploadedFile::fake()->image('cap.png', 250, 250)]))->assertSessionHasNoErrors();
    $first = PmbSignatory::current();
    $this->get(route('master-data.pmb-signatory.media', ['signatory' => $first, 'kind' => 'signature']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->post(route('master-data.pmb-signatory.update'), pmbProfile(['signature' => UploadedFile::fake()->image('ttd-baru.png', 360, 140)]))->assertSessionHasNoErrors();

    Storage::disk('local')->assertExists([$first->signature_path, $first->stamp_path, PmbSignatory::current()->signature_path]);
    expect(PmbSignatory::current()->signature_path)->not->toBe($first->signature_path);
    $this->get(route('master-data.pmb-signatory.media', ['signatory' => $first, 'kind' => 'signature']))->assertOk();
});

test('old letters retain their signatory images after the central profile is replaced', function () {
    $this->actingAs(pmbAdmin())->post(route('letters.store'), pmbLetter())->assertSessionHasNoErrors();
    $old = LetterBatch::query()->sole();
    $this->post(route('master-data.pmb-signatory.update'), pmbProfile(['name' => 'Ketua Baru, M.Pd', 'signature' => UploadedFile::fake()->image('baru.png')]))->assertSessionHasNoErrors();

    $this->post(route('letters.store'), pmbLetter(['first_sequence' => 2]))->assertSessionHasNoErrors();

    $this->get(route('letters.batch', $old))->assertInertia(fn (Assert $page) => $page->where('signatureOwnerName', 'Wardianto, M.Kom')->where('pmbSignatory.id', 1));
    $this->get(route('letters.permission.school', ['from_batch' => $old->id]))->assertInertia(fn (Assert $page) => $page->where('sourceDraft.data.signatory_name', 'Ketua Baru, M.Pd'));
    $new = LetterBatch::query()->latest('id')->first();
    $this->get(route('letters.batch', $new))->assertInertia(fn (Assert $page) => $page->where('signatureOwnerName', 'Ketua Baru, M.Pd')->where('pmbSignatory.id', 2));
});

test('attendance approvals retain the profile from when each event was created', function () {
    $this->actingAs(pmbAdmin());
    $event = ['type' => 'meeting', 'title' => 'Rapat PMB', 'event_date' => '2026-10-28', 'location' => 'Kampus A', 'is_open' => true];
    $this->post(route('attendance.store'), $event)->assertSessionHasNoErrors();
    $old = AttendanceEvent::query()->sole();
    $this->post(route('master-data.pmb-signatory.update'), pmbProfile(['name' => 'Ketua Baru, M.Pd']))->assertSessionHasNoErrors();

    $this->post(route('attendance.store'), $event)->assertSessionHasNoErrors();

    $this->get(route('attendance.print', $old))->assertSee('Wardianto, M.Kom')->assertDontSee('Ketua Baru, M.Pd');
    $this->get(route('attendance.print', AttendanceEvent::query()->latest('id')->first()))->assertSee('Ketua Baru, M.Pd')->assertDontSee('class="pmb-signature"', false);
});

test('a custom general letter signer cannot receive another persons automatic signature', function () {
    $this->actingAs(pmbAdmin())->post(route('letters.general.store'), array_replace(pmbLetter(), ['signatory_name' => 'Penandatangan Lain', 'include_signature' => true, 'include_stamp' => true, 'subject' => 'Undangan', 'body' => 'Isi surat', 'items' => [['recipient_name' => 'Undangan']]]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('letter_batches', ['include_signature' => false, 'include_stamp' => false]);
});

test('signature and stamp can be removed for future documents', function () {
    $this->actingAs(pmbAdmin())->post(route('master-data.pmb-signatory.update'), pmbProfile(['remove_signature' => true, 'remove_stamp' => true]))->assertSessionHasNoErrors();
    expect(PmbSignatory::current()->signature_path)->toBeNull();
    expect(PmbSignatory::current()->stamp_path)->toBeNull();
    $this->get(route('master-data.pmb-signatory.media', ['signatory' => PmbSignatory::current(), 'kind' => 'signature']))->assertNotFound();
});

test('invalid or unsafe uploads and conflicting choices leave the profile unchanged', function (string $scenario, string $field) {
    $file = match ($scenario) {
        'svg' => UploadedFile::fake()->createWithContent('ttd.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'large' => UploadedFile::fake()->image('ttd.png')->size(2049),
        'dimensions' => UploadedFile::fake()->image('ttd.png', 5001, 1),
        default => UploadedFile::fake()->image('ttd.png'),
    };
    $payload = pmbProfile(['signature' => $file]);
    if ($scenario === 'conflict') {
        $payload['remove_signature'] = true;
    }

    $this->actingAs(pmbAdmin())->post(route('master-data.pmb-signatory.update'), $payload)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('pmb_signatories', 1);
    expect(Storage::disk('local')->allFiles('pmb-signatories'))->toBe([]);
})->with([['svg', 'signature'], ['large', 'signature'], ['dimensions', 'signature'], ['conflict', 'signature']]);

test('a stale revision cannot overwrite the latest signatory', function () {
    $this->actingAs(pmbAdmin());
    $oldPayload = pmbProfile(['title' => 'Data Lama']);
    $this->post(route('master-data.pmb-signatory.update'), pmbProfile(['title' => 'Jabatan Terbaru']))->assertSessionHasNoErrors();

    $this->post(route('master-data.pmb-signatory.update'), $oldPayload)->assertSessionHasErrors('revision');

    expect(PmbSignatory::current()->title)->toBe('Jabatan Terbaru');
    $this->assertDatabaseCount('pmb_signatories', 2);
});

test('only admins can change the profile and guests cannot access its media', function (bool $guest) {
    if (! $guest) {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => User::ROLE_STAFF]));
    }
    foreach (['get', 'post'] as $method) {
        $response = $this->{$method}(route($method === 'get' ? 'master-data.pmb-signatory.edit' : 'master-data.pmb-signatory.update'), pmbProfile());
        $guest ? $response->assertRedirect(route('login')) : $response->assertForbidden();
    }
    if ($guest) {
        $this->get(route('master-data.pmb-signatory.media', ['signatory' => 1, 'kind' => 'signature']))->assertRedirect(route('login'));
    }
    $this->assertDatabaseCount('pmb_signatories', 1);
})->with([true, false]);
