<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterAssignee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterAssignee>
 */
class LetterAssigneeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'letter_id' => Letter::factory(),
            'name' => fake()->name(),
            'office' => 'Dosen',
            'district' => fake()->city(),
            'sort_order' => 0,
        ];
    }
}
