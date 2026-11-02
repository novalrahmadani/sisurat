<?php

namespace App\Policies;

use App\Models\QuranAssessment;
use App\Models\User;

class QuranAssessmentPolicy
{
    public function view(User $user, QuranAssessment $quranAssessment): bool
    {
        return $quranAssessment->created_by === (int) $user->id;
    }

    public function update(User $user, QuranAssessment $quranAssessment): bool
    {
        return $quranAssessment->created_by === (int) $user->id;
    }

    public function delete(User $user, QuranAssessment $quranAssessment): bool
    {
        return $quranAssessment->created_by === (int) $user->id;
    }
}
