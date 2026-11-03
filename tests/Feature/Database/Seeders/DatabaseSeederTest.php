<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('fills missing roles and creates the requested admin when seeding the database', function () {
    $legacyAccount = User::factory()->create(['role' => '']);
    $existingAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $email = 'new-admin@example.com';
    $password = 'SisuRat-Test-763!';

    $this->artisan('db:seed')
        ->expectsQuestion('Email admin SISURAT (Enter untuk lewati)', $email)
        ->expectsQuestion('Masukkan kata sandi baru', $password)
        ->expectsQuestion('Ulangi kata sandi baru', $password)
        ->assertSuccessful();

    $newAdmin = User::query()->where('email', $email)->firstOrFail();

    expect($legacyAccount->fresh()->role)->toBe(User::ROLE_STAFF);
    expect($existingAdmin->fresh()->isAdministrator())->toBeTrue();
    expect($newAdmin->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $newAdmin->password))->toBeTrue();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

it('keeps the existing account when the seeder reset is declined', function () {
    $account = User::factory()->create()->refresh();

    $this->artisan('db:seed', ['--class' => 'UserSeeder'])
        ->expectsQuestion('Email admin SISURAT (Enter untuk lewati)', $account->email)
        ->expectsConfirmation('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?', 'no')
        ->assertSuccessful();

    expect($account->fresh()->getAttributes())->toBe($account->getAttributes());
});

it('restores an existing admin account when the seeder reset is accepted', function () {
    $account = User::factory()->create();
    $password = 'Pemulihan-Admin-487!';

    $this->artisan('db:seed', ['--class' => 'UserSeeder'])
        ->expectsQuestion('Email admin SISURAT (Enter untuk lewati)', $account->email)
        ->expectsConfirmation('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?', 'yes')
        ->expectsQuestion('Masukkan kata sandi baru', $password)
        ->expectsQuestion('Ulangi kata sandi baru', $password)
        ->assertSuccessful();

    $restored = $account->fresh();

    expect($restored->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $restored->password))->toBeTrue();
    $this->assertDatabaseCount('users', 1);
});

it('skips account creation during noninteractive seeding', function () {
    $this->withoutMockingConsoleOutput();

    $exitCode = $this->artisan('db:seed', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    $this->assertDatabaseCount('users', 0);
});
