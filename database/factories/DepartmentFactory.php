<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'company_id' => Company::factory(),
            'name' => ucfirst($name),
            'slug' => $name,
            'settings' => [],
        ];
    }
}
