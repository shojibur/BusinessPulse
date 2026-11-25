<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fictional test fixtures only. Default password: "correct-horse-battery" (test use only).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'correct-horse-battery';

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'department_id' => null,
            'manager_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'role' => Role::Staff,
            'job_title' => null,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => Role::Owner, 'department_id' => null, 'manager_id' => null]);
    }

    public function manager(?Department $department = null): static
    {
        return $this->state(fn () => ['role' => Role::Manager] + $this->inDepartment($department));
    }

    public function staff(?Department $department = null): static
    {
        return $this->state(fn () => ['role' => Role::Staff] + $this->inDepartment($department));
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inDepartment(?Department $department): array
    {
        return $department
            ? ['company_id' => $department->company_id, 'department_id' => $department->id]
            : [];
    }
}
