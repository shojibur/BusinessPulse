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
        return [
            'name' => fake()->unique()->company().' Sdn Bhd',
            'timezone' => 'Asia/Kuala_Lumpur',
            'currency' => 'MYR',
            'trading_days' => [1, 2, 3, 4, 5, 6],
            'reporting_cutoff_time' => '12:00',
            'reporting_cutoff_lag_days' => 1,
            'is_demo' => false,
            'recording_retention_days' => 30,
        ];
    }
}
