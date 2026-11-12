<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'letter_batch_id',
        'institution_id',
        'sequence_number',
        'letter_number',
        'recipient_salutation',
        'recipient_position',
        'recipient_name',
        'institution_type',
        'institution_name',
        'recipient_district',
        'recipient_address',
        'target_class',
        'activity_destination',
        'participant_name',
        'participant_nim',
        'participant_study_program',
        'participant_faculty',
        'participant_result',
        'participation_status',
        'content_overrides',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
            'content_overrides' => 'array',
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function nextSequencesByYear(): array
    {
        $reservedDates = self::withTrashed()
            ->join('letter_batches', 'letters.letter_batch_id', '=', 'letter_batches.id')
            ->where('letters.numbering_scope', 'official')
            ->where('letter_batches.is_trial', false)
            ->select('letter_batches.letter_date')
            ->selectRaw('MAX(letters.sequence_number) AS last_sequence')
            ->groupBy('letter_batches.letter_date')
            ->toBase()->get();
        $nextSequences = [];
        foreach ($reservedDates as $reservedDate) {
            $year = substr((string) $reservedDate->letter_date, 0, 4);
            $nextSequences[$year] = max($nextSequences[$year] ?? 1, (int) $reservedDate->last_sequence + 1);
        }
        foreach (ManualOutgoingLetter::withTrashed()->select('letter_year')->selectRaw('MAX(sequence_number) AS last_sequence')->groupBy('letter_year')->toBase()->get() as $manual) {
            $year = (string) $manual->letter_year;
            $nextSequences[$year] = max($nextSequences[$year] ?? 1, (int) $manual->last_sequence + 1);
        }
        foreach (LetterNumbering::query()->get() as $numbering) {
            $year = (string) $numbering->year;
            $nextSequences[$year] = max($nextSequences[$year] ?? 1, (int) $numbering->next_sequence);
        }
        ksort($nextSequences, SORT_STRING);

        return $nextSequences;
    }

    public static function lastOfficialSequenceForYear(int $year): int
    {
        $systemMaximum = (int) (self::withTrashed()
            ->join('letter_batches', 'letters.letter_batch_id', '=', 'letter_batches.id')
            ->where('letters.numbering_scope', 'official')
            ->where('letter_batches.is_trial', false)
            ->whereYear('letter_batches.letter_date', $year)
            ->max('letters.sequence_number') ?? 0);

        return max($systemMaximum, (int) ManualOutgoingLetter::withTrashed()->where('letter_year', $year)->max('sequence_number'));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LetterBatch::class, 'letter_batch_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(LetterAssignee::class)->orderBy('sort_order');
    }
}
