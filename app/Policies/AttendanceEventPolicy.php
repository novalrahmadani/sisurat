<?php

namespace App\Policies;

use App\Models\AttendanceEvent;
use App\Models\User;

class AttendanceEventPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceEvent $attendanceEvent): bool
    {
        return $attendanceEvent->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttendanceEvent $attendanceEvent): bool
    {
        return $attendanceEvent->created_by === (int) $user->id;
    }

    public function delete(User $user, AttendanceEvent $attendanceEvent): bool
    {
        return $attendanceEvent->created_by === (int) $user->id;
    }
}
