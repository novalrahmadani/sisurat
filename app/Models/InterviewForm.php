<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property int|null $wave
 * @property string|null $period
 * @property string|null $campus
 * @property array<int, array{id: string, prompt: string, required: bool}> $questions
 */
class InterviewForm extends Model
{
    protected $fillable = ['wave', 'period', 'campus', 'title', 'description', 'questions', 'questions_version', 'is_open', 'public_token', 'created_by'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['wave' => 'integer', 'questions' => 'array', 'is_open' => 'boolean', 'created_by' => 'integer'];
    }

    /**
     * @return HasMany<InterviewSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(InterviewSubmission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
