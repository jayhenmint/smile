<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'school_id' => fn (array $attributes) => SchoolYear::withoutGlobalScopes()->findOrFail($attributes['school_year_id'])->school_id,
            'name' => fake()->unique()->bothify('Test section ????-####'),
            'grade_level' => 'Non-graded', 'placement_type' => 'sped_self_contained', 'adviser_id' => null,
        ];
    }
}
