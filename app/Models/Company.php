<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single business tenant of Stage 1.
 *
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property string $currency
 * @property list<int> $trading_days ISO weekdays 1 (Monday) to 7 (Sunday), sorted ascending
 * @property string $reporting_cutoff_time HH:MM
 * @property int $reporting_cutoff_lag_days
 * @property bool $is_demo
 * @property int $recording_retention_days
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reporting_cutoff_lag_days' => 'integer',
            'recording_retention_days' => 'integer',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * Trading days are stored as a sorted, de-duplicated JSON array of integers.
     *
     * @return Attribute<list<int>, list<int|string>>
     */
    protected function tradingDays(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => array_map('intval', json_decode($value ?? '[]', true)),
            set: function (array $days): string {
                $days = array_values(array_unique(array_map('intval', $days)));
                sort($days);

                return json_encode($days);
            },
        );
    }

    /**
     * The cutoff time as HH:MM (PostgreSQL returns HH:MM:SS).
     *
     * @return Attribute<string, string>
     */
    protected function reportingCutoffTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => substr($value ?? '12:00', 0, 5),
        );
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
