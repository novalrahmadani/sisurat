<?php

namespace Database\Factories;

use App\Models\InternshipSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipSetting>
 */
class InternshipSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['latitude' => -4.123, 'longitude' => 104.123, 'radius_meters' => 100];
    }
}
