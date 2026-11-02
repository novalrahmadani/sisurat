<?php

namespace Database\Factories;

use App\Models\Letter;
use App\Models\LetterBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Letter>
 */
class LetterFactory extends Factory
{
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 99999);
        $month = fake()->numberBetween(1, 12);
        $romanMonths = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return [
            'letter_batch_id' => LetterBatch::factory(),
            'sequence_number' => $sequence,
            'letter_number' => sprintf('%03d/PPMBPM-UNUHA/TU.2.1/%s/%s', $sequence, $romanMonths[$month], fake()->year()),
            'recipient_position' => 'Kepala',
            'recipient_name' => null,
            'institution_type' => fake()->randomElement(['school', 'pesantren']),
            'institution_name' => fake()->company(),
            'recipient_district' => null,
            'target_class' => null,
            'activity_destination' => null,
        ];
    }

    public function legacyAssignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'recipient_position' => null,
            'recipient_name' => null,
            'institution_type' => null,
            'institution_name' => null,
            'recipient_district' => null,
            'target_class' => null,
            'activity_destination' => 'sekolah-sekolah tingkat SMA, SMK, dan MA',
        ]);
    }
}
