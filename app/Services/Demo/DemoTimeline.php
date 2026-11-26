<?php

namespace App\Services\Demo;

use App\Actions\Outcomes\SubmitFeedback;
use App\Actions\Tasks\ApproveTaskDraft;
use App\Actions\Tasks\ChangeTaskStatus;
use App\Actions\Tasks\RejectTaskDraft;
use App\Enums\BriefStatus;
use App\Enums\MeetingStatus;
use App\Enums\TaskDraftStatus;
use App\Enums\TaskStatus;
use App\Events\Meetings\MeetingCreated;
use App\Jobs\GenerateBriefJob;
use App\Models\Brief;
use App\Models\Company;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Outcome;
use App\Models\Task;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\AI\AiSettings;
use App\Services\AI\BriefFingerprint;
use App\Services\Meetings\MeetingTranscriptService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Plays the rest of the demo story (28 Sep to 12 Oct) through the REAL application code: the meeting transcript and
 * extraction job, human approvals, task progress, the week 9 and week 10 imports, outcome verification, briefs and
 * feedback. The demo clock (and Carbon, so created_at values match) steps forward between actions.
 *
 * No live provider is ever called. The AI is pinned to a recorded sample: the extraction reply comes from
 * database/demo/samples/*.json and still goes through the real validation (excerpts must appear in the transcript, people
 * must resolve to real staff, dates are computed by the server). Briefs run with no AI, so they use the documented
 * deterministic fallback. The AI sample never decides who gets a task: a Manager approves each draft.
 */
class DemoTimeline
{
    /** @var array<string, mixed> */
    private array $reply = [];

    public const SECOND_MEETING_TITLE = 'Weekly recovery follow-up';

    public function __construct(
        private readonly ApproveTaskDraft $approve,
        private readonly RejectTaskDraft $reject,
        private readonly ChangeTaskStatus $status,
        private readonly SubmitFeedback $feedback,
        private readonly BriefFingerprint $fingerprints,
    ) {}

    public function play(Company $company): void
    {
        $owner = User::query()->where('company_id', $company->id)->where('role', 'owner')->orderBy('id')->firstOrFail();
        $manager = User::query()->where('company_id', $company->id)->where('role', 'manager')->orderBy('id')->firstOrFail();
        $staff = fn (string $email): User => User::query()->where('company_id', $company->id)->where('email', $email)->firstOrFail();
        $priya = $staff('priya@meridian.demo');
        $farid = $staff('farid@meridian.demo');
        $danielLee = $staff('daniel.lee@meridian.demo');

        try {
            // Mon 28 Sep: the owner reads the first brief, then the huddle happens.
            $this->at('2026-09-28', '09:20');
            $this->brief($owner, null);

            $this->at('2026-09-28', '11:30');
            $huddle = Meeting::query()->where('title', DemoSeeder::MEETING_TITLE)->firstOrFail();
            $this->transcribe($huddle, $manager, 'meeting-transcript.txt', 'extraction-huddle.json');
            $issue = Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->firstOrFail();

            $this->at('2026-09-28', '15:10');
            $this->approveDraft($huddle, 'Call the ten biggest', $manager, ['issue_id' => $issue->id]);
            $this->approveDraft($huddle, 'Prepare the JB promo', $manager, ['issue_id' => $issue->id]);
            // The ambiguous "Daniel" and the unowned stock check stay pending: a human must decide, the AI never assigns.

            // Priya and Farid work their tasks.
            $this->at('2026-09-29', '10:05');
            $this->move($this->task('Call the ten biggest'), TaskStatus::InProgress, $priya);
            $this->at('2026-09-30', '09:40');
            $this->move($this->task('Prepare the JB promo'), TaskStatus::InProgress, $farid);
            $this->at('2026-10-02', '11:15');
            $this->move($this->task('Call the ten biggest'), TaskStatus::Completed, $priya);
            $this->at('2026-10-02', '16:30');
            $this->move($this->task('Prepare the JB promo'), TaskStatus::Completed, $farid);

            // Mon 5 Oct: week 9 arrives (during action) and a second, smaller meeting is held for the department.
            $this->at('2026-10-05', '09:00');
            $this->run('demo:import', ['fixture' => 'week9-interim']);

            $this->at('2026-10-05', '14:00');
            $followUp = Meeting::query()->create([
                'department_id' => $manager->department_id,
                'created_by' => $manager->id,
                'title' => self::SECOND_MEETING_TITLE,
                'meeting_date' => '2026-10-05',
                'status' => MeetingStatus::Draft,
            ]);
            MeetingCreated::dispatch($followUp, $manager->id);
            $this->transcribe($followUp, $manager, 'meeting-transcript-2.txt', 'extraction-followup.json');

            $this->at('2026-10-05', '16:20');
            $this->approveDraft($followUp, 'Book extra weekend staff', $manager, ['assignee_id' => $danielLee->id, 'due_date' => '2026-10-08']);
            $this->approveDraft($followUp, 'Send the Penang order summary', $manager, ['assignee_id' => $priya->id, 'due_date' => '2026-10-09']);
            $this->rejectDraft($followUp, 'Update the shelf labels', $manager, 'The Ipoh store is outside this department.');

            $this->at('2026-10-07', '09:30');
            $this->move($this->task('Send the Penang order summary'), TaskStatus::InProgress, $priya);

            // Mon 12 Oct: week 10 arrives (post action). The real verification marks the recovery.
            $this->at('2026-10-12', '09:00');
            $this->run('demo:import', ['fixture' => 'week10-recovery']);

            $outcome = Outcome::query()->orderByDesc('id')->first();
            $issue->refresh();

            if ($outcome === null || $issue->status->value !== 'resolved') {
                throw new DemoSeedException('Week 10 did not produce the documented recovery outcome and a system-resolved issue.');
            }

            $this->at('2026-10-12', '09:45');
            $this->feedback->handle($owner, $outcome, true, 'The follow-up calls clearly worked.');
            $this->brief($owner, null);
            $this->brief($manager, $manager->department_id);
        } finally {
            Carbon::setTestNow();
            AiSettings::pin(null);
        }
    }

    private function brief(User $user, ?int $departmentId): void
    {
        AiSettings::pin(AiSettings::MODE_OFF); // no AI: the deterministic fallback summary

        $brief = Brief::query()->create([
            'company_id' => $user->company_id,
            'department_id' => $departmentId,
            'requested_by' => $user->id,
            'status' => BriefStatus::Queued,
            'source_fingerprint' => $this->fingerprints->for($user),
        ]);
        GenerateBriefJob::dispatchSync($brief->id, $user->company_id);

        if ($brief->fresh()->status !== BriefStatus::Completed) {
            throw new DemoSeedException('A demo brief did not complete.');
        }
    }

    private function transcribe(Meeting $meeting, User $actor, string $transcriptFile, string $sampleFile): void
    {
        $sample = json_decode((string) file_get_contents(base_path("database/demo/samples/{$sampleFile}")), true, 512, JSON_THROW_ON_ERROR);
        // Http::fake keeps the FIRST stub that matches, so one stub reads whichever sample is current.
        $this->reply = ['choices' => [['message' => ['content' => json_encode($sample)]]]];

        AiSettings::pin(AiSettings::MODE_SAMPLE);
        Http::fake([parse_url(AiSettings::SAMPLE_URL, PHP_URL_HOST).'/*' => fn () => Http::response($this->reply)]);

        $stored = app(MeetingTranscriptService::class)
            ->storeManual($meeting, $actor, trim((string) file_get_contents(base_path("database/demo/{$transcriptFile}"))));

        AiSettings::pin(null);

        if (! $stored || $meeting->fresh()->status !== MeetingStatus::ReadyForReview) {
            throw new DemoSeedException("Extraction of \"{$meeting->title}\" did not finish with the recorded sample.");
        }
    }

    /** @param  array<string, mixed>  $edits */
    private function approveDraft(Meeting $meeting, string $titleStart, User $actor, array $edits): void
    {
        $this->approve->handle($this->draft($meeting, $titleStart), $actor, $edits);
    }

    private function rejectDraft(Meeting $meeting, string $titleStart, User $actor, string $reason): void
    {
        $this->reject->handle($this->draft($meeting, $titleStart), $actor, $reason);
    }

    private function draft(Meeting $meeting, string $titleStart): TaskDraft
    {
        return TaskDraft::query()->where('meeting_id', $meeting->id)->where('status', TaskDraftStatus::Pending->value)
            ->where('suggested_title', 'like', $titleStart.'%')->firstOrFail();
    }

    private function task(string $titleStart): Task
    {
        return Task::query()->where('title', 'like', $titleStart.'%')->firstOrFail();
    }

    private function move(Task $task, TaskStatus $to, User $actor): void
    {
        $this->status->handle($task, $to, $actor);
    }

    private function at(string $date, string $time): void
    {
        $this->run('demo:set-date', ['date' => $date, '--time' => $time]);
        Carbon::setTestNow(CarbonImmutable::parse("{$date} {$time}", (string) config('business.timezone')));
    }

    /** @param  array<string, mixed>  $arguments */
    private function run(string $command, array $arguments): void
    {
        if (Artisan::call($command, $arguments) !== 0) {
            throw new DemoSeedException("{$command} failed: ".trim(preg_replace('/\s+/', ' ', Artisan::output()) ?? ''));
        }
    }
}
