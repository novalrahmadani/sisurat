<?php

namespace App\Models;

use Database\Factories\ManualOutgoingLetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualOutgoingLetter extends Model
{
    /** @use HasFactory<ManualOutgoingLetterFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['created_by', 'recipient', 'letter_date', 'subject', 'letter_number', 'sequence_number', 'letter_year', 'type', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['letter_date' => 'date', 'sequence_number' => 'integer', 'letter_year' => 'integer', 'created_by' => 'integer', 'version' => 'integer'];
    }

    /** @return array{sequence: int, year: int}|null */
    public static function numberParts(string $number): ?array
    {
        if (preg_match('~\A([0-9]{1,5})/[A-Za-z0-9][A-Za-z0-9 ._/-]*/([0-9]{4})\z~D', trim($number), $matches) !== 1) {
            return null;
        }
        $sequence = (int) $matches[1];
        $year = (int) $matches[2];

        return $sequence >= 1 && $year >= 2000 && $year <= 2100 ? ['sequence' => $sequence, 'year' => $year] : null;
    }
}
