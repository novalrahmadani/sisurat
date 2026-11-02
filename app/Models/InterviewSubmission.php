<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int|null $wave
 * @property string|null $academic_year
 * @property string|null $period
 * @property string|null $campus
 * @property string $name
 * @property string $registration_number
 * @property string $study_program
 * @property Carbon $submitted_at
 * @property array<int, array{question_id: string, question: string, required: bool, answer: string}> $answers
 */
class InterviewSubmission extends Model
{
    protected $fillable = ['wave', 'period', 'academic_year', 'campus', 'interview_form_id', 'name', 'registration_number', 'registration_key', 'study_program', 'answers', 'submission_token', 'submitted_at'];

    protected $hidden = ['submission_token', 'registration_key'];

    protected function casts(): array
    {
        return ['wave' => 'integer', 'interview_form_id' => 'integer', 'answers' => 'array', 'submitted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<InterviewForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(InterviewForm::class, 'interview_form_id');
    }

    /** @return array{wave: int|null, period: string|null, academic_year: string|null, campus: string|null} */
    public function testSessionDetails(): array
    {
        $period = $this->period ?? $this->form?->period;
        $academicYear = $this->academic_year;
        if (($academicYear === null || $this->period === null) && is_string($period) && preg_match('/^[0-9]{4}\/[0-9]{4}$/D', trim($period)) === 1) {
            $academicYear ??= trim($period);
            $period = null;
        }

        return ['wave' => $this->wave ?? $this->form?->wave, 'period' => $period,
            'academic_year' => $academicYear, 'campus' => $this->campus ?? $this->form?->campus];
    }

    public static function registrationKey(string $registrationNumber): string
    {
        return hash('sha256', mb_strtolower(Str::squish($registrationNumber)));
    }
}
