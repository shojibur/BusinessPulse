<?php

namespace App\Services\Activity;

use App\Enums\ActivityStage;
use App\Models\ActivityLog;
use App\Models\Brief;
use App\Models\Import;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Outcome;
use App\Models\RuleEvaluation;
use App\Models\Task;
use App\Models\User;
use App\Services\Outcomes\OutcomeWording;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The trace of one issue from imported sales data to feedback, built from PERSISTED records and activity rows only. Nothing
 * is invented for work done before activity recording existed: a stage with nothing to show says so. Titles and names come
 * from the linked records, never from activity properties. Visibility follows the policies; Staff get null.
 */
final class TraceTimelineBuilder
{
    private const LIMIT = 500;

    /**
     * @return array{stages: list<array<string, mixed>>, truncated: bool}|null
     */
    public function build(Issue $issue, User $actor): ?array
    {
        if ($actor->isStaff()) {
            return null;
        }

        $issue->loadMissing('department.company');
        $timezone = $issue->department->company->timezone;

        $outcomes = Outcome::query()->where('issue_id', $issue->id)->get();
        $evaluationIds = array_values(array_unique([$issue->baseline_evaluation_id, ...$outcomes->pluck('followup_evaluation_id')->all()]));
        $evaluations = RuleEvaluation::query()->whereIn('id', $evaluationIds)->get();
        $importIds = $evaluations->flatMap(fn (RuleEvaluation $e) => $e->evidence['contributingImportIds'] ?? [])
            ->merge($outcomes->flatMap(fn (Outcome $o) => [...($o->evidence['baseline']['contributingImportIds'] ?? []), ...($o->evidence['followup']['contributingImportIds'] ?? [])]))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        $briefIds = DB::table('brief_items')->where('issue_id', $issue->id)->pluck('brief_id')->unique()->values()->all();
        $meetingIds = DB::table('meeting_issues')->where('issue_id', $issue->id)->pluck('meeting_id')->all();
        $tasks = Task::query()->where('issue_id', $issue->id)->get();

        $activity = ActivityLog::query()
            ->where('company_id', $actor->company_id)
            ->where('action', '!=', 'import.file_downloaded')
            ->where(fn ($q) => $q->where('issue_id', $issue->id)
                ->orWhere(fn ($q) => $q->where('subject_type', 'Import')->whereIn('subject_id', $importIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'RuleEvaluation')->whereIn('subject_id', $evaluationIds))
                ->orWhere(fn ($q) => $q->where('subject_type', 'Brief')->whereIn('subject_id', $briefIds)))
            ->orderBy('created_at')->orderBy('id')->limit(self::LIMIT)->get();

        $imports = Import::query()->whereIn('id', $importIds)->get()->keyBy('id');
        $briefs = Brief::query()->whereIn('id', $briefIds)->get()->keyBy('id');
        $meetings = Meeting::query()->whereIn('id', $meetingIds)->get()->keyBy('id');
        $taskById = $tasks->keyBy('id');
        $outcomeById = $outcomes->keyBy('id');
        $names = User::query()->whereIn('id', $activity->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        $can = fn ($model) => $model !== null && Gate::forUser($actor)->allows('view', $model);
        $subject = fn (string $type, int $id): array => $this->subject($type, $id, $imports, $briefs, $meetings, $taskById, $outcomeById, $evaluations, $can);

        $entries = [];
        $seen = [];

        foreach ($activity as $row) {
            $type = (string) $row->subject_type;

            // Hide rows of records the viewer may not open (briefs, meetings, tasks).
            if ($type === 'Brief' && ! $can($briefs->get($row->subject_id))) {
                continue;
            }
            if ($type === 'Meeting' && ! $can($meetings->get($row->subject_id))) {
                continue;
            }
            if ($type === 'Task' && ! $can($taskById->get($row->subject_id))) {
                continue;
            }

            $seen[$type.':'.$row->subject_id] = true;
            $at = $row->created_at->setTimezone($timezone);
            $entries[] = [
                'key' => 'a'.$row->id, 'kind' => 'activity', 'sort' => $at->getTimestamp(), 'tie' => $row->id,
                'stage' => ActivityStage::forAction($row->action),
                'at' => $at->toIso8601String(), 'atLabel' => $at->format('j M Y, H:i'),
                'label' => $this->label($row->action, $row->properties ?? []),
                'userName' => $row->user_id === null ? 'System' : ($names[$row->user_id] ?? 'System'),
                'phaseLabel' => $type === 'Outcome' ? $this->phaseLabel($outcomeById->get($row->subject_id)) : null,
                'subject' => $subject($type, (int) $row->subject_id),
            ];
        }

        // Record entries keep every stage populated from the linked records even where no activity row exists.
        $records = [];

        foreach ($imports as $m) {
            $records[] = ['Import', $m->id, $m->created_at, 'Import uploaded', ActivityStage::BusinessData];
        }
        foreach ($evaluations as $m) {
            $records[] = ['RuleEvaluation', $m->id, $m->evaluated_at, 'Rule evaluated', ActivityStage::RuleEvaluation];
        }
        foreach ($briefs as $m) {
            $can($m) && $records[] = ['Brief', $m->id, $m->created_at, 'Brief created', ActivityStage::Brief];
        }
        foreach ($meetings as $m) {
            $can($m) && $records[] = ['Meeting', $m->id, $m->created_at, 'Meeting created', ActivityStage::Meeting];
        }
        foreach ($tasks as $m) {
            $can($m) && $records[] = ['Task', $m->id, $m->created_at, 'Task created', ActivityStage::Task];
        }
        foreach ($outcomes as $m) {
            $records[] = ['Outcome', $m->id, $m->evaluated_at, 'Observed change recorded', ActivityStage::Outcome];
        }

        foreach ($records as [$type, $id, $time, $label, $stage]) {
            if (isset($seen[$type.':'.$id]) || $time === null) {
                continue;
            }

            $at = $time->setTimezone($timezone);
            $entries[] = [
                'key' => 'r-'.$type.'-'.$id, 'kind' => 'record', 'sort' => $at->getTimestamp(), 'tie' => 0,
                'stage' => $stage, 'at' => $at->toIso8601String(), 'atLabel' => $at->format('j M Y, H:i'), 'label' => $label,
                'userName' => 'System', 'phaseLabel' => $type === 'Outcome' ? $this->phaseLabel($outcomeById->get($id)) : null,
                'subject' => $subject($type, (int) $id),
            ];
        }

        $stages = [];
        foreach (ActivityStage::cases() as $stage) {
            $list = collect($entries)->where('stage', $stage)->sortBy([['sort', 'asc'], ['tie', 'asc'], ['key', 'asc']])
                ->map(fn (array $e) => collect($e)->except(['stage', 'sort', 'tie'])->all())->values()->all();
            $stages[] = ['stage' => $stage->value, 'stageLabel' => $stage->label(), 'empty' => $list === [], 'entries' => $list];
        }

        return ['stages' => $stages, 'truncated' => $activity->count() >= self::LIMIT];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function label(string $action, array $properties): string
    {
        $replace = [];
        foreach ($properties as $key => $value) {
            $replace[$key] = is_bool($value) ? ($value ? 'yes' : 'no') : str_replace('_', ' ', (string) $value);
        }

        return trans('activity.'.$action, $replace);
    }

    private function phaseLabel(?Outcome $outcome): ?string
    {
        return $outcome === null ? null : OutcomeWording::phase($outcome->phase);
    }

    /**
     * @param  Collection<int, mixed>  $imports
     * @return array{type: string, id: int, title: string|null, url: string|null}
     */
    private function subject(string $type, int $id, Collection $imports, Collection $briefs, Collection $meetings, Collection $tasks, Collection $outcomes, Collection $evaluations, callable $can): array
    {
        [$title, $url] = match ($type) {
            'Import' => [$imports->get($id)?->original_filename, $can($imports->get($id)) ? route('imports.show', $id) : null],
            'Brief' => [$briefs->get($id)?->headline, $can($briefs->get($id)) ? route('briefs.show', $id) : null],
            'Meeting' => [$meetings->get($id)?->title, $can($meetings->get($id)) ? route('meetings.show', $id) : null],
            'Task' => [$tasks->get($id)?->title, $can($tasks->get($id)) ? route('tasks.show', $id) : null],
            'Outcome' => [null, $outcomes->has($id) ? route('outcomes.show', $id) : null],
            default => [null, null],
        };

        return ['type' => $type, 'id' => $id, 'title' => $title, 'url' => $url];
    }
}
