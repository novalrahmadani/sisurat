<?php

namespace App\Policies;

use App\Models\LetterBatch;
use App\Models\User;

class LetterBatchPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LetterBatch $letterBatch): bool
    {
        return (int) $letterBatch->user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LetterBatch $letterBatch): bool
    {
        return (int) $letterBatch->user_id === (int) $user->id;
    }
}
