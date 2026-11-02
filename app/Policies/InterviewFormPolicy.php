<?php

namespace App\Policies;

use App\Models\InterviewForm;
use App\Models\User;

class InterviewFormPolicy
{
    public function view(User $user, InterviewForm $interviewForm): bool
    {
        return $interviewForm->created_by === (int) $user->id;
    }

    public function update(User $user, InterviewForm $interviewForm): bool
    {
        return $interviewForm->created_by === (int) $user->id;
    }

    public function delete(User $user, InterviewForm $interviewForm): bool
    {
        return $interviewForm->created_by === (int) $user->id;
    }
}
