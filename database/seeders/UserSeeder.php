<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command === null) {
            return;
        }

        $email = Str::lower(trim((string) $this->command->ask('Email admin SISURAT (Enter untuk lewati)')));

        if ($email === '') {
            $this->command->info('Penyiapan akun admin dilewati.');

            return;
        }

        $accountExists = User::query()->where('email', $email)->exists();

        if ($accountExists && ! $this->command->confirm('Akun sudah ada. Pulihkan akses admin dan atur kata sandi baru?')) {
            $this->command->info('Akun yang sudah ada tetap digunakan.');

            return;
        }

        $result = $this->command->call('accounts:provision-admin', [
            'email' => $email,
            '--reset-password' => $accountExists,
        ]);

        if ($result !== Command::SUCCESS) {
            throw new RuntimeException('Penyiapan akun admin gagal. Periksa pesan sebelumnya dan jalankan UserSeeder kembali.');
        }
    }
}
