<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmbProspectSetting extends Model
{
    protected $fillable = ['is_open'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }
}

