<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmbProspectSubmission extends Model
{
    protected $fillable = [
        'name',
        'study_program',
        'school_name',
        'school_district',
        'school_address',
        'affiliate_name',
        'submission_token',
        'submitted_at',
    ];

    protected $hidden = ['submission_token'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
