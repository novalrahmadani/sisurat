<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(LazilyRefreshDatabase::class);

it('fills missing roles and creates the requested admin when seeding the database', function () {
    $legacyAccount = User::factory()->create(['role' => '']);
    $existingAdmin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    $email = 'new-admin@example.com';
    $password = 'SisuRat-Test-763!';

    $this->artisan('db:seed')
        ->expectsQuestion('Email admin SISURAT (ketik - untuk lewati)', $email)
        ->expectsConfirmation('Buat kata sandi otomatis?', 'no')
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
        ->expectsQuestion('Email admin SISURAT (ketik - untuk lewati)', $account->email)
        ->expectsConfirmation('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?', 'no')
        ->assertSuccessful();

    expect($account->fresh()->getAttributes())->toBe($account->getAttributes());
});

it('restores an existing admin account when the seeder reset is accepted', function () {
    $account = User::factory()->create();
    $password = 'Pemulihan-Admin-487!';

    $this->artisan('db:seed', ['--class' => 'UserSeeder'])
        ->expectsQuestion('Email admin SISURAT (ketik - untuk lewati)', $account->email)
        ->expectsConfirmation('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?', 'yes')
        ->expectsConfirmation('Buat kata sandi otomatis?', 'no')
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

it('creates an admin from hosting configuration without prompts', function () {
    $email = 'hosting-admin@example.com';
    $password = 'Hosting-Test-628!';
    config(['admin-seeder.email' => $email, 'admin-seeder.password' => $password]);

    $this->artisan('db:seed', ['--class' => 'UserSeeder', '--no-interaction' => true])
        ->doesntExpectOutput($password)
        ->assertSuccessful();

    $account = User::query()->where('email', $email)->firstOrFail();

    expect($account->isAdministrator())->toBeTrue();
    expect(Hash::check($password, $account->password))->toBeTrue();
});

it('keeps an existing admin password when the configured seeder runs again', function () {
    $account = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true])->refresh();
    config(['admin-seeder.email' => $account->email, 'admin-seeder.password' => 'Hosting-Test-628!']);

    $this->artisan('db:seed', ['--class' => 'UserSeeder', '--no-interaction' => true])
        ->assertSuccessful();

    expect($account->fresh()->getAttributes())->toBe($account->getAttributes());
});

it('rejects incomplete admin configuration without creating an account', function () {
    config(['admin-seeder.email' => 'hosting-admin@example.com', 'admin-seeder.password' => null]);

    $this->withoutMockingConsoleOutput();

    expect(fn () => $this->artisan('db:seed', ['--class' => 'UserSeeder', '--no-interaction' => true]))
        ->toThrow(RuntimeException::class, 'Isi SISURAT_ADMIN_EMAIL dan SISURAT_ADMIN_PASSWORD');
    $this->assertDatabaseCount('users', 0);
});

it('uses the default email and displays a generated password when both defaults are accepted', function () {
    $input = new ArrayInput(['command' => 'db:seed', '--class' => 'UserSeeder', '--force' => true]);
    $inputStream = fopen('php://memory', 'r+');
    fwrite($inputStream, "\n\n");
    rewind($inputStream);
    $input->setStream($inputStream);
    $output = new BufferedOutput;

    $exitCode = app(Kernel::class)->handle($input, $output);
    fclose($inputStream);

    expect($exitCode)->toBe(0);

    $account = User::query()->where('email', 'admin12@gmail.com')->firstOrFail();
    $matched = preg_match('/^Kata sandi baru: (\S+)\r?$/m', $output->fetch(), $matches);

    expect($account->isAdministrator())->toBeTrue();
    expect($matched)->toBe(1);
    expect(Hash::check($matches[1], $account->password))->toBeTrue();
});
