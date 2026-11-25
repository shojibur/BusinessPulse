<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A human "useful / not useful" vote on an issue or an outcome. It never changes a rule, a threshold or a classification. */
class Feedback extends Model
{
    protected $table = 'feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['useful' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
