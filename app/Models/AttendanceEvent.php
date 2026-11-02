<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceEvent extends Model
{
    public const TYPE_MEETING = 'meeting';

    public const TYPE_PMB_TEST = 'pmb_test';

    public const TYPE_OTHER = 'other';

    public const TYPES = [self::TYPE_MEETING, self::TYPE_PMB_TEST, self::TYPE_OTHER];

    protected $fillable = ['type', 'title', 'event_date', 'location', 'description', 'is_open', 'public_token', 'created_by', 'pmb_signatory_id'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['event_date' => 'date', 'is_open' => 'boolean', 'created_by' => 'integer'];
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pmbSignatory(): BelongsTo
    {
        return $this->belongsTo(PmbSignatory::class);
    }
}
