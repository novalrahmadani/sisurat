<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'institution_type',
        'name',
        'district',
        'district_id',
        'address',
        'source_number',
        'seed_key',
    ];

    protected function casts(): array
    {
        return [
            'source_number' => 'integer',
            'district_id' => 'integer',
        ];
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function districtRecord(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
