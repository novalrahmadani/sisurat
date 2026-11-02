<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, User $account): bool
    {
        return $user->isAdministrator()
            && (! $account->is_primary_admin || (int) $user->id === (int) $account->id);
    }

    public function delete(User $user, User $account): bool
    {
        return false;
    }
}
