<?php

namespace Database\Factories;

use App\Models\ManualOutgoingLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ManualOutgoingLetter> */
class ManualOutgoingLetterFactory extends Factory
{
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 99999);

        return ['created_by' => User::factory(), 'recipient' => fake()->company(), 'letter_date' => '2026-10-29', 'subject' => 'Surat Undangan Rapat', 'letter_number' => sprintf('%03d/UNUHA.11/TU.2.1/X/2026', $sequence), 'sequence_number' => $sequence, 'letter_year' => 2026, 'type' => 'general', 'version' => 1];
    }
}
