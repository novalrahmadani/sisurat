<?php

namespace Database\Factories;

use App\Models\InternshipParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<InternshipParticipant>
 */
class InternshipParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'student_number' => fake()->unique()->numerify('##########'),
            'institution' => 'Universitas Contoh',
            'study_program' => 'Informatika',
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-13',
            'is_active' => true,
            'access_code_hash' => Hash::make('KODEMAGANG12'),
        ];
    }
}
