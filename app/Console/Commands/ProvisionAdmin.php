<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('accounts:provision-admin {email : Email akun admin} {--reset-password : Pulihkan akses admin dan atur ulang kata sandi akun yang sudah ada}')]
#[Description('Siapkan akun admin SISURAT dengan kata sandi yang dimasukkan di terminal')]
class ProvisionAdmin extends Command
{
    use PasswordValidationRules;

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Jalankan perintah di terminal interaktif agar kata sandi bisa dimasukkan secara tersembunyi.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $account = User::query()->firstOrNew(['email' => $email]);
        $resetPassword = (bool) $this->option('reset-password');

        if ($account->exists && ! $resetPassword) {
            $this->error('Akun sudah ada. Gunakan --reset-password untuk memulihkan aksesnya.');

            return self::FAILURE;
        }

        if (! $account->exists && $resetPassword) {
            $this->error('Akun belum ada. Jalankan perintah tanpa --reset-password untuk membuatnya.');

            return self::FAILURE;
        }

        $passwordValidator = Validator::make([
            'password' => $this->secret('Masukkan kata sandi baru', false),
            'password_confirmation' => $this->secret('Ulangi kata sandi baru', false),
        ], ['password' => $this->passwordRules()]);

        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        if (! $account->exists) {
            $account->name = 'Admin SISURAT';
        }

        $account->forceFill([
            'password' => $passwordValidator->validated()['password'],
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'remember_token' => null,
        ])->save();

        $this->info("Akun admin {$email} aktif dan siap digunakan.");

        return self::SUCCESS;
    }
}
