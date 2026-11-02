<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = ['attendance_event_id', 'name', 'position', 'institution', 'registration_number', 'study_program', 'school_origin', 'phone_number', 'signature_png', 'submission_token', 'signed_at'];

    protected $hidden = ['signature_png', 'submission_token'];

    protected function casts(): array
    {
        return ['attendance_event_id' => 'integer', 'signed_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }
}
