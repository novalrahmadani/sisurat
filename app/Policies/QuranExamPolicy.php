<?php

namespace App\Policies;

use App\Models\QuranExam;
use App\Models\User;

class QuranExamPolicy
{
    public function view(User $user, QuranExam $quranExam): bool
    {
        return $quranExam->created_by === (int) $user->id;
    }

    public function update(User $user, QuranExam $quranExam): bool
    {
        return $this->view($user, $quranExam);
    }
}
