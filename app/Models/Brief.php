<?php

namespace App\Models;

use App\Enums\AudioStatus;
use App\Enums\BriefSource;
use App\Enums\BriefStatus;
use App\Enums\CoverageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A company-level executive brief. It has no department_id (a Manager's brief is scoped at generation time).
 * `input_snapshot` holds only what was sent to the provider; `error_message` only a sanitized code.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $requested_by
 * @property BriefStatus $status
 * @property BriefSource|null $source
 * @property Carbon|null $as_of_date
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property CoverageStatus|null $coverage_status
 * @property string|null $headline
 * @property string|null $summary_text
 * @property array<string, mixed>|null $input_snapshot
 * @property string|null $model
 * @property string|null $error_message
 * @property AudioStatus $audio_status
 * @property string|null $audio_path
 * @property string|null $audio_model
 * @property string|null $audio_voice
 * @property Carbon|null $generated_at
 */
class Brief extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => BriefStatus::class,
            'source' => BriefSource::class,
            'coverage_status' => CoverageStatus::class,
            'audio_status' => AudioStatus::class,
            'as_of_date' => 'date:Y-m-d',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'input_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /** @param  Builder<Brief>  $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', BriefStatus::Completed->value);
    }

    /** @return HasMany<BriefItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BriefItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
