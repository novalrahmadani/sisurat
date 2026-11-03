<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $updated = User::query()
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => User::ROLE_STAFF]);

        $this->command?->info("{$updated} akun tanpa peran diberi peran staf.");
    }
}
