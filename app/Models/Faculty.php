<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $fillable = ['name', 'identity_key'];

    public static function normalizeName(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public static function identityKey(string $name): string
    {
        return hash('sha256', mb_strtolower(self::normalizeName($name), 'UTF-8'));
    }
}
