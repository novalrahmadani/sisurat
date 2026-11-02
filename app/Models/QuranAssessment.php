<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $assessment_date
 * @property int|null $wave
 * @property string|null $academic_year
 * @property string|null $period
 * @property string|null $campus
 */
class QuranAssessment extends Model
{
    public const RESULT_PENDING = 'pending';

    public const RESULT_FLUENT = 'fluent';

    public const RESULT_FAIR = 'fair';

    public const RESULT_NEEDS_GUIDANCE = 'needs_guidance';

    public const RESULT_MQ1 = 'mq1';

    public const RESULT_MQ2 = 'mq2';

    public const MQ_RESULTS = [self::RESULT_MQ1, self::RESULT_MQ2];

    public const RESULTS = [
        ...self::MQ_RESULTS,
        self::RESULT_PENDING,
        self::RESULT_FLUENT,
        self::RESULT_FAIR,
        self::RESULT_NEEDS_GUIDANCE,
    ];

    public const RESULT_LABELS = [
        self::RESULT_MQ1 => 'MQ 1',
        self::RESULT_MQ2 => 'Berhak mengikuti MQ 2',
        self::RESULT_PENDING => 'Menunggu penilaian MQ',
        self::RESULT_FLUENT => 'Penilaian lama: Lancar',
        self::RESULT_FAIR => 'Penilaian lama: Cukup',
        self::RESULT_NEEDS_GUIDANCE => 'Penilaian lama: Perlu bimbingan',
    ];

    protected $fillable = [
        'name', 'registration_number', 'study_program', 'assessment_date',
        'examiner_name', 'reading_reference', 'fasohah_score', 'tajwid_score', 'makhraj_score',
        'fluency_score', 'result', 'notes', 'created_by', 'version', 'submission_token',
        'quran_exam_id', 'registration_key', 'wave', 'period', 'academic_year', 'campus',
    ];

    protected $hidden = ['submission_token', 'registration_key'];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'fasohah_score' => 'integer',
            'makhraj_score' => 'integer',
            'tajwid_score' => 'integer',
            'fluency_score' => 'integer',
            'created_by' => 'integer',
            'quran_exam_id' => 'integer',
            'wave' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<QuranExam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(QuranExam::class, 'quran_exam_id');
    }

    /** @return array{wave: int|null, period: string|null, academic_year: string|null, campus: string|null} */
    public function testSessionDetails(): array
    {
        $session = $this->quran_exam_id === null ? $this : ($this->exam ?? $this);

        $period = $this->period ?? $session->period;
        $academicYear = $this->academic_year;
        if (($academicYear === null || $this->period === null) && is_string($period) && preg_match('/^[0-9]{4}\/[0-9]{4}$/D', trim($period)) === 1) {
            $academicYear ??= trim($period);
            $period = null;
        }

        return ['wave' => $this->wave ?? $session->wave, 'period' => $period,
            'academic_year' => $academicYear, 'campus' => $this->campus ?? $session->campus];
    }

    public static function registrationKey(string $number): string
    {
        return hash('sha256', mb_strtoupper(trim($number), 'UTF-8'));
    }

    public function totalScore(): ?int
    {
        return self::completeTotal([$this->fasohah_score, $this->tajwid_score, $this->makhraj_score, $this->fluency_score]);
    }

    public function averageScore(): ?float
    {
        $total = $this->totalScore();

        return $total === null ? null : $total / 4;
    }

    public static function resultForScores(?int $fasohah, ?int $tajwid, ?int $makhraj, ?int $fluency): string
    {
        $total = self::completeTotal([$fasohah, $tajwid, $makhraj, $fluency]);

        return $total === null ? self::RESULT_PENDING : ($total >= 280 ? self::RESULT_MQ2 : self::RESULT_MQ1);
    }

    /** @param list<int|null> $scores */
    private static function completeTotal(array $scores): ?int
    {
        if (count($scores) !== 4 || in_array(null, $scores, true) || min($scores) < 1 || max($scores) > 100) {
            return null;
        }

        return array_sum($scores);
    }
}
