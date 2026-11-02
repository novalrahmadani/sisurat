<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $internship_participant_id
 * @property CarbonImmutable $attendance_date
 * @property CarbonImmutable $checked_in_at
 * @property CarbonImmutable|null $checked_out_at
 * @property string $check_in_signature
 * @property string|null $check_out_signature
 * @property string|null $check_in_note
 * @property string|null $check_out_note
 * @property-read InternshipParticipant $participant
 */
class InternshipAttendance extends Model
{
    protected $fillable = ['internship_participant_id', 'attendance_date', 'checked_in_at', 'checked_out_at', 'check_in_signature', 'check_out_signature', 'check_in_note', 'check_out_note'];

    protected $hidden = ['check_in_signature', 'check_out_signature'];

    protected function casts(): array
    {
        return ['attendance_date' => 'immutable_date', 'checked_in_at' => 'immutable_datetime', 'checked_out_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<InternshipParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(InternshipParticipant::class, 'internship_participant_id');
    }

    /** @return array{id: int, date: string, checked_in_at: string, checked_out_at: string|null, duration_minutes: int|null, check_in_note: string|null, check_out_note: string|null} */
    public function presentation(): array
    {
        return [
            'id' => $this->id, 'date' => $this->attendance_date->toDateString(),
            'checked_in_at' => $this->checked_in_at->timezone('Asia/Jakarta')->format('H:i:s'),
            'checked_out_at' => $this->checked_out_at?->timezone('Asia/Jakarta')->format('H:i:s'),
            'duration_minutes' => $this->checked_out_at ? (int) $this->checked_in_at->diffInMinutes($this->checked_out_at) : null,
            'check_in_note' => $this->check_in_note, 'check_out_note' => $this->check_out_note,
        ];
    }
}
