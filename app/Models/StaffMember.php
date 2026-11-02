<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffMember extends Model
{
    protected $fillable = ['name', 'office', 'employee_number', 'district_id'];

    protected function casts(): array
    {
        return ['district_id' => 'integer'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
