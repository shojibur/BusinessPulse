<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only trace row. ActivityRecorder is the only writer; there is no update or delete path. */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only.'));
    }
}
