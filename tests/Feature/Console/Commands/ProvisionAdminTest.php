<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('creates an active admin with the supplied password', function () {
    $password = 'SisuRat-Test-341!';

    $this->artisan('accounts:provision-admin', ['email' => '  Admin@Example.com  '])
        ->expectsQuestion('Masukkan kata sandi baru', $password)
        ->expectsQuestion('Ulangi kata sandi baru', $password)
        ->assertSuccessful();

    $account = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($account->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $account->password))->toBeTrue();
});

it('leaves an existing account untouched unless a reset is requested', function () {
    $account = User::factory()->create()->refresh();

    $this->artisan('accounts:provision-admin', ['email' => $account->email])
        ->expectsOutput('Akun sudah ada. Gunakan --reset-password untuk memulihkan aksesnya.')
        ->assertFailed();

    expect($account->fresh()->getAttributes())->toBe($account->getAttributes());
});

it('restores only the selected account when a reset is requested', function () {
    $account = User::factory()->create();
    $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true, 'is_primary_admin' => true])->refresh();
    $password = 'Pemulihan-Admin-573!';

    $this->artisan('accounts:provision-admin', ['email' => $account->email, '--reset-password' => true])
        ->expectsQuestion('Masukkan kata sandi baru', $password)
        ->expectsQuestion('Ulangi kata sandi baru', $password)
        ->assertSuccessful();

    $restored = $account->fresh();

    expect($restored->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $restored->password))->toBeTrue();
    expect($restored->remember_token)->toBeNull();
    expect($otherAdmin->fresh()->getAttributes())->toBe($otherAdmin->getAttributes());
});

it('does not create an admin when the passwords differ', function () {
    $email = 'admin@example.com';

    $this->artisan('accounts:provision-admin', ['email' => $email])
        ->expectsQuestion('Masukkan kata sandi baru', 'SisuRat-Test-341!')
        ->expectsQuestion('Ulangi kata sandi baru', 'SisuRat-Test-342!')
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => $email]);
});

it('does not create an account when a reset is requested for a missing email', function () {
    $email = 'admin@example.com';

    $this->artisan('accounts:provision-admin', ['email' => $email, '--reset-password' => true])
        ->expectsOutput('Akun belum ada. Jalankan perintah tanpa --reset-password untuk membuatnya.')
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => $email]);
});

it('rejects an invalid admin email before prompting for a password', function () {
    $this->artisan('accounts:provision-admin', ['email' => 'invalid-email'])
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

it('requires an interactive terminal to create an admin', function () {
    $this->artisan('accounts:provision-admin', ['email' => 'admin@example.com', '--no-interaction' => true])
        ->expectsOutput('Jalankan perintah di terminal interaktif agar kata sandi bisa dimasukkan secara tersembunyi.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

it('resets the configured account password only when requested explicitly', function () {
    $account = User::factory()->create();
    $password = 'Hosting-Test-628!';
    config(['admin-seeder.email' => $account->email, 'admin-seeder.password' => $password]);

    $this->artisan('accounts:provision-admin', [
        '--from-config' => true,
        '--reset-password' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $restored = $account->fresh();

    expect($restored->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $restored->password))->toBeTrue();
});
