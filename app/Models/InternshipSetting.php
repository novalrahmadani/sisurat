<?php

namespace App\Models;

use Database\Factories\InternshipSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $radius_meters
 */
class InternshipSetting extends Model
{
    /** @use HasFactory<InternshipSettingFactory> */
    use HasFactory;

    protected $fillable = ['latitude', 'longitude', 'radius_meters'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius_meters' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
