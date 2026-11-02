<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LetterBatch extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const MAX_LETTERS_PER_BATCH = 100;

    public const DEFAULT_NUMBER_PREFIX = 'UNUHA.11/TU.2.1';

    protected $fillable = [
        'user_id',
        'pmb_signatory_id',
        'type',
        'letter_date',
        'first_sequence',
        'number_prefix',
        'subject',
        'attachment',
        'body',
        'general_options',
        'pkkmb_options',
        'content_templates',
        'event_start',
        'event_end',
        'event_location',
        'academic_year',
        'signatory_title',
        'signatory_name',
        'signatory_number',
        'include_signature',
        'include_stamp',
    ];

    protected function casts(): array
    {
        return [
            'general_options' => 'array',
            'is_trial' => 'boolean',
            'pkkmb_options' => 'array',
            'content_templates' => 'array',
            'letter_date' => 'date',
            'event_start' => 'date',
            'event_end' => 'date',
            'include_signature' => 'boolean',
            'include_stamp' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function pmbSignatory(): BelongsTo
    {
        return $this->belongsTo(PmbSignatory::class);
    }
}
