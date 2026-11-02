<?php

namespace Database\Factories;

use App\Models\LetterBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterBatch>
 */
class LetterBatchFactory extends Factory
{
    public function definition(): array
    {
        $letterDate = fake()->date();

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['permission', 'assignment']),
            'letter_date' => $letterDate,
            'first_sequence' => fake()->numberBetween(1, 999),
            'number_prefix' => 'PPMBPM-UNUHA/TU.2.1',
            'event_start' => $letterDate,
            'event_end' => $letterDate,
            'signatory_title' => 'Ketua Pusat PMB',
            'signatory_name' => fake()->name(),
            'signatory_number' => fake()->numerify('NIP.Y ##########.##.###'),
        ];
    }

    public function assignment(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'assignment']);
    }
}
