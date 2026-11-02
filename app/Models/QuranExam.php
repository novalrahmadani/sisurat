<?php

namespace App\Models;

use Database\Factories\QuranExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $assessment_date
 * @property int $assessments_count
 * @property int|null $wave
 * @property string|null $period
 * @property string|null $campus
 */
class QuranExam extends Model
{
    /** @use HasFactory<QuranExamFactory> */
    use HasFactory;

    protected $fillable = ['wave', 'period', 'campus', 'title', 'description', 'assessment_date', 'is_open', 'created_by', 'public_token', 'version'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['wave' => 'integer', 'assessment_date' => 'date', 'is_open' => 'boolean', 'created_by' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuranAssessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(QuranAssessment::class);
    }
}
