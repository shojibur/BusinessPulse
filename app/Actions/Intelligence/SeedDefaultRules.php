<?php

namespace App\Actions\Intelligence;

use App\Enums\RuleKey;
use App\Models\BusinessRule;
use App\Models\Department;

/**
 * Creates the three predefined rules of a department with their default configuration. Idempotent and never
 * overwrites an existing rule. Thresholds and severities are demonstration defaults, not client requirements.
 */
final class SeedDefaultRules
{
    /**
     * @return list<BusinessRule>
     */
    public function handle(Department $department): array
    {
        return $this->ensureRulesExist($department->id);
    }

    /**
     * @return list<BusinessRule>
     */
    public function ensureRulesExist(int $departmentId): array
    {
        $rules = [];

        foreach ($this->defaults() as $key => $defaults) {
            // firstOrCreate: an existing rule keeps whatever configuration it has.
            $rules[] = BusinessRule::query()->firstOrCreate(
                ['department_id' => $departmentId, 'key' => $key],
                $defaults,
            );
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaults(): array
    {
        return [
            RuleKey::WeeklyRevenueDecline->value => [
                'name' => 'Weekly revenue decline',
                'threshold' => '15.00',
                'severity' => 'medium',
                'is_active' => true,
                'params' => ['high_drop_pct' => '25.00'],
            ],
            RuleKey::SalesTargetShortfall->value => [
                'name' => 'Sales target shortfall',
                'threshold' => '80.00',
                'severity' => 'medium',
                'is_active' => true,
                'params' => ['high_below_pct' => '70.00'],
            ],
            RuleKey::MissingSalesData->value => [
                'name' => 'Missing sales data',
                'threshold' => null,
                'severity' => 'medium',
                'is_active' => true,
                'params' => [],
            ],
        ];
    }
}
