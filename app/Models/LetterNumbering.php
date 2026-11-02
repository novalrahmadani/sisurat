<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterNumbering extends Model
{
    protected $fillable = ['year', 'next_sequence', 'updated_by', 'configured_at'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'next_sequence' => 'integer', 'configured_at' => 'datetime'];
    }

    public static function lockForYear(int $year): self
    {
        self::query()->firstOrCreate(['year' => $year], ['next_sequence' => 1]);

        return self::query()->where('year', $year)->lockForUpdate()->firstOrFail();
    }

    public function minimumNextSequence(): int
    {
        return max((int) $this->next_sequence, Letter::lastOfficialSequenceForYear((int) $this->year) + 1);
    }
}
