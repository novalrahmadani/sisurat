<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        throw new AuthorizationException('Pendaftaran akun umum ditutup. Hubungi administrator SISURAT.');
    }
}
