<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    private const DEFAULT_ADMIN_EMAIL = 'admin12@gmail.com';

    public function run(): void
    {
        if ($this->command === null) {
            return;
        }

        $configuredEmail = config('admin-seeder.email');
        $configuredPassword = config('admin-seeder.password');
        $fromConfig = filled($configuredEmail) || filled($configuredPassword);

        if (! $fromConfig && $this->command->option('no-interaction')) {
            $this->command->info('Penyiapan akun admin dilewati.');

            return;
        }

        if ($fromConfig && (blank($configuredEmail) || blank($configuredPassword))) {
            throw new RuntimeException('Isi SISURAT_ADMIN_EMAIL dan SISURAT_ADMIN_PASSWORD pada konfigurasi hosting.');
        }

        $email = Str::lower(trim((string) ($fromConfig
            ? $configuredEmail
            : $this->command->ask('Email admin SISURAT (ketik - untuk lewati)', self::DEFAULT_ADMIN_EMAIL))));

        if ($email === '' || (! $fromConfig && $email === '-')) {
            $this->command->info('Penyiapan akun admin dilewati.');

            return;
        }

        $accountExists = User::query()->where('email', $email)->exists();

        if ($accountExists && $fromConfig) {
            $this->command->info('Akun sudah ada. Gunakan accounts:provision-admin --from-config --reset-password untuk memulihkannya.');

            return;
        }

        if ($accountExists && ! $this->command->confirm('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?')) {
            $this->command->info('Akun yang sudah ada tetap digunakan.');

            return;
        }

        $generatePassword = ! $fromConfig && $this->command->confirm('Buat kata sandi otomatis?', true);

        $result = $this->command->call('accounts:provision-admin', [
            'email' => $email,
            '--reset-password' => $accountExists,
            '--from-config' => $fromConfig,
            '--generate-password' => $generatePassword,
        ]);

        if ($result !== Command::SUCCESS) {
            throw new RuntimeException('Penyiapan akun admin gagal. Periksa pesan sebelumnya dan jalankan UserSeeder kembali.');
        }
    }
}
