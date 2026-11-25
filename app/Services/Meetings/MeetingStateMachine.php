<?php

namespace App\Services\Meetings;

use App\Enums\FailureStage;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use LogicException;

/**
 * The only code that writes meetings.status. Every transition is one compare-and-set UPDATE ... WHERE status IN (from),
 * so two simultaneous requests or a duplicate job can move a meeting only once.
 */
final class MeetingStateMachine
{
    private const ALLOWED = [
        'draft' => ['uploaded', 'transcribed'],
        // uploaded/transcribed -> failed: a job that dies before it claims the meeting (design deviation, see S06 notes).
        'uploaded' => ['transcribing', 'failed'],
        'transcribing' => ['transcribed', 'failed'],
        'transcribed' => ['extracting', 'failed'],
        'extracting' => ['ready_for_review', 'failed'],
        'failed' => ['uploaded', 'transcribed', 'extracting'],
        'ready_for_review' => ['extracting', 'transcribed'],
    ];

    /**
     * @param  MeetingStatus|list<MeetingStatus>  $from
     * @param  array<string, mixed>  $attributes  extra columns written in the same UPDATE
     * @param  array<string, mixed>  $where  extra equality conditions of the same compare-and-set (null means IS NULL)
     * @return bool true when exactly one row moved, false when another request won the race
     */
    public function transition(int $meetingId, MeetingStatus|array $from, MeetingStatus $to, array $attributes = [], ?FailureStage $requireStage = null, array $where = []): bool
    {
        $from = is_array($from) ? $from : [$from];

        foreach ($from as $status) {
            if (! (self::ALLOWED[$status->value] ?? false) || ! in_array($to->value, self::ALLOWED[$status->value], true)) {
                throw new LogicException("Meeting transition {$status->value} -> {$to->value} is not allowed.");
            }
        }

        if ($to === MeetingStatus::Failed) {
            $stage = $attributes['failure_stage'] ?? throw new LogicException('A failed meeting needs a failure stage.');
            $attributes['failure_stage'] = $stage instanceof FailureStage ? $stage->value : $stage;
        } else {
            $attributes['failure_stage'] = null;
            $attributes += ['error_message' => null];
        }

        $query = Meeting::query()->whereKey($meetingId)->whereIn('status', array_map(fn (MeetingStatus $s) => $s->value, $from));

        if ($requireStage !== null) {
            $query->where('failure_stage', $requireStage->value);
        }

        foreach ($where as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query->update(['status' => $to->value, 'updated_at' => now()] + $this->encode($attributes)) === 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function encode(array $attributes): array
    {
        // Query-builder updates bypass model casts: arrays become JSON.
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $attributes;
    }
}
