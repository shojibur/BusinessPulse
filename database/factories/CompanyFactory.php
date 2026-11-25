<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictional test fixtures only.
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company().' Sdn Bhd'];
    }
}
