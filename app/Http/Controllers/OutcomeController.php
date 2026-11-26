<?php

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Enums\OutcomeResult;
use App\Http\Requests\OutcomeIndexRequest;
use App\Models\Feedback;
use App\Models\Outcome;
use App\Models\Task;
use App\Models\User;
use App\Services\Outcomes\IssueOutcomeProps;
use App\Services\Outcomes\OutcomePresenter;
use App\Services\Outcomes\OutcomeWording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Outcomes list and comparison view. Every figure comes from the stored outcome row and its evidence snapshot. */
class OutcomeController extends Controller
{
    public function __construct(private readonly OutcomePresenter $presenter) {}

    public function index(OutcomeIndexRequest $request): Response
    {
        Gate::authorize('viewAny', Outcome::class);
        $user = $request->user();
        $timezone = $user->company->timezone;

        $latestIds = DB::table('outcomes')->selectRaw('DISTINCT ON (issue_id) id')->orderBy('issue_id')->orderByDesc('evaluated_at')->orderByDesc('id');
        $query = Outcome::query()->visibleTo($user)->whereIn('outcomes.id', DB::query()->fromSub($latestIds, 'l')->select('id'))
            ->with(['issue.rule:id,name,key', 'issue.department:id,name'])
            ->when($request->result() !== null, fn ($q) => $q->where('result', $request->result()))
            ->when($request->status() !== null, fn ($q) => $q->whereHas('issue', fn ($i) => $i->where('status', $request->status())))
            ->orderByDesc('evaluated_at')->orderByDesc('id');

        $page = $query->paginate(15)->withQueryString();

        return Inertia::render('outcomes/Index', [
            'outcomes' => Inertia::defer(fn () => $page->getCollection()->map(fn (Outcome $o) => $this->presenter->summary($o, $timezone) + [
                'issueId' => $o->issue_id, 'issueTitle' => $o->issue->title, 'ruleName' => $o->issue->rule->name, 'departmentName' => $o->issue->department->name,
                'issueStatus' => $o->issue->status->value, 'issueStatusLabel' => $o->issue->status->label(),
            ])->values()),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_url' => $page->previousPageUrl(), 'next_url' => $page->nextPageUrl()],
            'filters' => ['result' => $request->result(), 'status' => $request->status()],
            'options' => [
                'results' => array_map(fn (OutcomeResult $r) => ['value' => $r->value, 'label' => OutcomeWording::result($r)], OutcomeResult::cases()),
                'statuses' => array_map(fn (IssueStatus $s) => ['value' => $s->value, 'label' => $s->label()], IssueStatus::cases()),
            ],
        ]);
    }

    public function show(Request $request, string $outcome): Response
    {
        Gate::authorize('viewAny', Outcome::class); // Staff: 403
        $user = $request->user();
        $o = Outcome::query()->visibleTo($user)->with(['issue.rule', 'issue.department.company'])->find($outcome);
        abort_if($o === null, 404); // a missing and an out-of-scope id are the same 404

        $issue = $o->issue;
        $timezone = $issue->department->company->timezone;
        $e = $o->evidence;
        $money = $o->outcome_metric->isMoney();
        $unit = $money ? 'MYR' : '%';
        $fmt = fn (?string $v) => $v === null ? null : ($money ? OutcomeWording::money($v) : $v.'%');
        $limit = $e['condition']['limit4'] ?? null;
        $limitLabel = $limit === null ? null : ($money ? OutcomeWording::money(bcadd($limit, '0', 2)) : bcadd($limit, '0', 2).'%');
        $range = fn (?array $a) => $a === null ? null : ['start' => $a['start'] ?? $a[0], 'end' => $a['end'] ?? $a[1]];
        $short = fn (string $a, string $b) => date('j M', strtotime($a)).' to '.date('j M', strtotime($b));

        $b = $e['baseline'];
        $f = $e['followup'];
        $chart = $o->comparable ? [
            'categories' => $money
                ? ['Pre-decline '.$short($b['referencePeriod']['start'], $b['referencePeriod']['end']), 'Baseline '.$short($b['periodStart'], $b['periodEnd']), 'Follow-up '.$short($f['periodStart'], $f['periodEnd'])]
                : ['Baseline '.$short($b['periodStart'], $b['periodEnd']), 'Follow-up '.$short($f['periodStart'], $f['periodEnd'])],
            'values' => $money ? [$o->reference_value, $o->baseline_value, $o->followup_value] : [$o->baseline_value, $o->followup_value],
            'unit' => $unit, 'metricLabel' => OutcomeWording::metric($o->outcome_metric),
        ] : null;

        $votes = Feedback::query()->where('outcome_id', $o->id)->with('user:id,name')->orderBy('id')->get();
        $summary = $this->presenter->summary($o, $timezone);

        return Inertia::render('outcomes/Show', [
            'outcome' => [
                'id' => $o->id, 'heading' => OutcomeWording::HEADING, 'standingSentence' => OutcomeWording::STANDING,
                'issue' => ['id' => $issue->id, 'title' => $issue->title, 'status' => $issue->status->value, 'statusLabel' => $issue->status->label(), 'url' => route('issues.show', $issue->id),
                    'severity' => $issue->severity->value, 'detectedLabel' => $issue->first_detected_at->setTimezone($timezone)->format('j M Y')],
                'ruleName' => $issue->rule->name, 'departmentName' => $issue->department->name,
                'outcomeMetric' => $o->outcome_metric->value, 'outcomeMetricLabel' => OutcomeWording::metric($o->outcome_metric),
                'baselinePeriod' => ['start' => $b['periodStart'], 'end' => $b['periodEnd']],
                'followupPeriod' => ['start' => $f['periodStart'], 'end' => $f['periodEnd']],
                'referencePeriod' => $money ? $range($b['referencePeriod'] ?? null) : null,
                'phase' => ['code' => $o->phase->value, 'label' => OutcomeWording::phase($o->phase), 'usedForVerification' => $o->phase->usedForVerification(),
                    'actionDate' => $e['phase']['actionDate'], 'monitoringDate' => $e['phase']['monitoringDate']],
                'values' => [
                    'baseline' => $o->baseline_value, 'followup' => $o->followup_value, 'reference' => $o->reference_value,
                    'absoluteChange' => $o->absolute_change, 'relativeChangePct' => $o->relative_change_pct, 'pointChange' => $o->point_change,
                    'recoveredSharePct' => $e['recoveredSharePct'] ?? null, 'unit' => $unit,
                    'baselineLabel' => $fmt($o->baseline_value), 'followupLabel' => $fmt($o->followup_value), 'referenceLabel' => $fmt($o->reference_value),
                    'changeLabels' => $summary['changeLabels'],
                ],
                'result' => $o->result->value, 'resultLabel' => OutcomeWording::result($o->result), 'notEvaluableText' => $summary['notEvaluableText'],
                'comparable' => $o->comparable,
                'condition' => ['met' => $o->resolution_condition_met, 'text' => $o->resolution_condition_met ? 'Condition for resolution met' : 'Condition for resolution not met', 'limitLabel' => $limitLabel],
                'weeklyChangeEvidence' => isset($e['weeklyChangeEvidence']) ? ['baselinePct' => $e['weeklyChangeEvidence']['baselinePct4'], 'followupPct' => $e['weeklyChangeEvidence']['followupPct4']] : null,
                'evaluatedAtLabel' => $summary['evaluatedAtLabel'],
                'checks' => array_map(fn (array $c) => $c + ['label' => OutcomeWording::check($c['name'])], $e['checks']),
                'evidence' => ['thresholdUsed' => $b['thresholdUsed'], 'coverage' => $e['week'] ?? null, 'band' => $e['band']],
                'manualNote' => $o->manual_note === null ? null : ['text' => $o->manual_note, 'byName' => User::query()->whereKey($o->manual_note_by)->value('name'), 'atLabel' => $o->manual_note_at?->setTimezone($timezone)->format('j M Y, H:i')],
            ],
            'chart' => $chart,
            // The activity trace of the issue (the same data as the issue page), loaded after the page.
            'trace' => Inertia::defer(fn () => app(IssueOutcomeProps::class)->trace($issue, $user)),
            'otherOutcomes' => Outcome::query()->where('issue_id', $issue->id)->where('id', '!=', $o->id)->orderByDesc('evaluated_at')->orderByDesc('id')->get()
                ->map(fn (Outcome $x) => ['id' => $x->id, 'resultLabel' => OutcomeWording::result($x->result), 'phaseLabel' => OutcomeWording::phase($x->phase), 'evaluatedAtLabel' => $x->evaluated_at->setTimezone($timezone)->format('j M Y, H:i'), 'url' => route('outcomes.show', $x->id)])->values(),
            'tasks' => Task::query()->where('issue_id', $issue->id)->with('assignee:id,name')->orderBy('due_date')->limit(20)->get()
                ->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'assigneeName' => $t->assignee?->name, 'dueDate' => $t->due_date->format('Y-m-d'), 'statusLabel' => $t->status->label(), 'url' => route('tasks.show', $t->id)])->values(),
            'statusHistory' => collect($issue->status_history)->filter(fn ($h) => in_array($h['trigger'] ?? null, ['outcome_verified', 'outcome_worsened'], true))
                ->map(fn ($h) => ['at' => $h['at'], 'atLabel' => date('j M Y, H:i', strtotime($h['at'])), 'from' => $h['from'], 'to' => $h['to'], 'byName' => 'System', 'reason' => $h['reason'] ?? null])->values(),
            'feedback' => [
                'mine' => $votes->firstWhere('user_id', $user->id)?->useful,
                'counts' => ['useful' => $votes->where('useful', true)->count(), 'notUseful' => $votes->where('useful', false)->count()],
                'votes' => $votes->map(fn (Feedback $v) => ['userName' => $v->user->name, 'useful' => $v->useful, 'note' => $v->note, 'atLabel' => $v->updated_at->setTimezone($timezone)->format('j M Y, H:i')])->values(),
            ],
            'urls' => ['saveNote' => null, 'submitFeedback' => route('outcomes.feedback.store', $o->id), 'index' => route('outcomes.index')],
        ]);
    }
}
