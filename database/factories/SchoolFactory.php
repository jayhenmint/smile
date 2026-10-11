<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deped_school_id' => fake()->unique()->numerify('######'),
            'name' => fake()->company().' School',
            'division' => 'Test Division',
            'region' => 'Test Region',
            'address' => null,
        ];
    }
}
