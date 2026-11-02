<?php

namespace Database\Factories;

use App\Models\QuranExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<QuranExam> */
class QuranExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Tes Baca Al-Qur’an Gelombang 1',
            'description' => 'Isi nilai setelah peserta menyelesaikan bacaan.',
            'assessment_date' => today()->format('Y-m-d'), 'is_open' => true,
            'created_by' => User::factory()->state(['is_active' => true]),
            'public_token' => Str::random(64), 'version' => (string) Str::uuid(),
        ];
    }
}
