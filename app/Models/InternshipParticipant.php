<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InternshipParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $student_number
 * @property string $institution
 * @property string|null $study_program
 * @property string|null $participant_category
 * @property string|null $class_level
 * @property string $registration_source
 * @property string|null $device_token_hash
 * @property CarbonImmutable|null $device_expires_at
 * @property string|null $recovery_token_hash
 * @property CarbonImmutable|null $recovery_expires_at
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property bool $is_active
 * @property string|null $access_code_hash
 * @property string|null $pin_lookup_hash
 */
class InternshipParticipant extends Model
{
    /** @use HasFactory<InternshipParticipantFactory> */
    use HasFactory;

    public const CATEGORIES = ['smk' => 'Siswa SMK', 'sma' => 'Siswa SMA', 'university' => 'Mahasiswa'];

    protected $fillable = ['name', 'student_number', 'institution', 'study_program', 'participant_category', 'class_level', 'registration_source', 'start_date', 'end_date', 'is_active', 'access_code_hash', 'pin_lookup_hash', 'device_token_hash', 'device_expires_at', 'recovery_token_hash', 'recovery_expires_at'];

    protected $hidden = ['access_code_hash', 'pin_lookup_hash', 'device_token_hash', 'device_expires_at', 'recovery_token_hash', 'recovery_expires_at'];

    protected function casts(): array
    {
        return ['start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'device_expires_at' => 'immutable_datetime', 'recovery_expires_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->participant_category] ?? 'Belum dikategorikan';
    }

    /** @return HasMany<InternshipAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(InternshipAttendance::class);
    }

    public function canAttend(string $date): bool
    {
        return $this->is_active && $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }
}
