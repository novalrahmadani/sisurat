<?php

namespace App\Policies;

use App\Models\ManualOutgoingLetter;
use App\Models\User;

class ManualOutgoingLetterPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, ManualOutgoingLetter $entry): bool
    {
        return $user->is_active && ($user->isAdministrator() || $entry->created_by === $user->id);
    }

    public function delete(User $user, ManualOutgoingLetter $entry): bool
    {
        return $this->update($user, $entry);
    }
}
