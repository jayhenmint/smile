<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'label' => fake()->unique()->bothify('Test year ####-????'),
            'start_date' => '2026-06-08',
            'end_date' => '2027-03-31',
            'is_current' => false,
        ];
    }
}
