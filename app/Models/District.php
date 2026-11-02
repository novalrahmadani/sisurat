<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = ['name', 'identity_key'];

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public static function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $name = preg_replace('/^(?:kabupaten|kab|kota)(?:\.\s*|\s+)/iu', '', $value) ?? $value;
        $aliases = [
            'OKI' => 'Kabupaten Ogan Komering Ilir',
            'OGAN KOMERING ILIR' => 'Kabupaten Ogan Komering Ilir',
            'OKU' => 'Kabupaten Ogan Komering Ulu',
            'OGAN KOMERING ULU' => 'Kabupaten Ogan Komering Ulu',
            'OKUT' => 'Kabupaten Ogan Komering Ulu Timur',
            'OKU TIMUR' => 'Kabupaten Ogan Komering Ulu Timur',
            'OGAN KOMERING ULU TIMUR' => 'Kabupaten Ogan Komering Ulu Timur',
            'OKUS' => 'Kabupaten Ogan Komering Ulu Selatan',
            'OKU SELATAN' => 'Kabupaten Ogan Komering Ulu Selatan',
            'OGAN KOMERING ULU SELATAN' => 'Kabupaten Ogan Komering Ulu Selatan',
            'WAY KANAN' => 'Kabupaten Way Kanan',
            'MUARA ENIM' => 'Kabupaten Muara Enim',
            'PRABUMULIH' => 'Kota Prabumulih',
        ];

        if (isset($aliases[mb_strtoupper($name, 'UTF-8')])) {
            return $aliases[mb_strtoupper($name, 'UTF-8')];
        }

        if (preg_match('/^kota(?:\.\s*|\s+)/iu', $value) === 1) {
            return 'Kota '.$name;
        }

        if (preg_match('/^(?:kabupaten|kab)(?:\.\s*|\s+)/iu', $value) === 1) {
            return 'Kabupaten '.$name;
        }

        return $value;
    }

    public static function identityKey(string $name): string
    {
        return hash('sha256', mb_strtolower(self::normalizeName($name), 'UTF-8'));
    }
}
