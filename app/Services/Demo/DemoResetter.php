<?php

namespace App\Services\Demo;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * DESTRUCTIVE: deletes the whole demonstration company and re-seeds it. Serialized by a cache lock, refused while any queued
 * job exists, and silenced by a reset flag. Every deletion selects rows only through the demo company (company_id,
 * department_id or a parent that resolves to it); the transaction rolls back on any error. The caller has already passed
 * DemoGuard (exactly one company and it is the demo company).
 */
final class DemoResetter
{
    public function __construct(private readonly DemoResetFlag $flag, private readonly DemoSeeder $seeder) {}

    /** @throws DemoSeedException when the reset cannot run or fails (nothing is half-deleted) */
    public function reset(bool $populated = false, bool $full = false): ResetSummary
    {
        $lock = Cache::lock(DemoResetFlag::LOCK, 600);

        if (! $lock->get()) {
            throw new DemoSeedException('Another demo reset is running. Nothing was changed.');
        }

        try {
            $this->assertQueueEmpty();
            $this->flag->set();
            $this->assertQueueEmpty(); // a job dispatched while the flag was being set

            $company = Company::query()->where('is_demo', true)->firstOrFail();
            $files = $this->collectFiles($company);

            try {
                $deleted = DB::transaction(fn () => $this->deleteRows($company));
            } catch (Throwable $e) {
                throw new DemoSeedException('The reset failed and was rolled back; nothing was deleted ('.class_basename($e).').');
            }

            [$filesDeleted, $undeleted] = $this->deleteFiles($files);
            $this->flag->clear(); // the seed runs its jobs in-process and would otherwise exit without effect
            $seed = $this->seeder->seed($populated, $full);

            return new ResetSummary($deleted, $filesDeleted, $undeleted, $seed, (int) DB::table('failed_jobs')->count());
        } finally {
            $this->flag->clear();
            $lock->release();
        }
    }

    private function assertQueueEmpty(): void
    {
        $jobs = (int) DB::table('jobs')->count();

        if ($jobs > 0) {
            $this->flag->clear();

            throw new DemoSeedException("The jobs table holds {$jobs} row(s). Wait for the queue workers to drain the queue, then run demo:reset again. Nothing was changed.");
        }
    }

    /** @return list<string> private-disk paths that belong to the demo company */
    private function collectFiles(Company $company): array
    {
        $departments = DB::table('departments')->where('company_id', $company->id)->pluck('id');
        $paths = DB::table('imports')->whereIn('department_id', $departments)->whereNotNull('stored_path')->pluck('stored_path')
            ->merge(DB::table('meetings')->whereIn('department_id', $departments)->whereNotNull('recording_path')->pluck('recording_path'))
            ->merge(DB::table('briefs')->where('company_id', $company->id)->whereNotNull('audio_path')->pluck('audio_path'))
            ->push((string) config('demo.clock_file'));

        return $paths->filter()->unique()->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function deleteRows(Company $company): array
    {
        $c = $company->id;
        $departments = DB::table('departments')->where('company_id', $c)->pluck('id')->all();
        $users = DB::table('users')->where('company_id', $c)->pluck('id')->all();
        $issues = DB::table('issues')->whereIn('department_id', $departments)->pluck('id')->all();
        $meetings = DB::table('meetings')->whereIn('department_id', $departments)->pluck('id')->all();
        $tasks = DB::table('tasks')->whereIn('department_id', $departments)->pluck('id')->all();
        $outcomes = DB::table('outcomes')->whereIn('issue_id', $issues)->pluck('id')->all();
        $briefs = DB::table('briefs')->where('company_id', $c)->pluck('id')->all();
        $imports = DB::table('imports')->whereIn('department_id', $departments)->pluck('id')->all();

        // Break the reference cycles first (drafts <-> tasks, issue recurrence), without touching other rows.
        DB::table('task_drafts')->whereIn('meeting_id', $meetings)->update(['task_id' => null]);
        DB::table('issues')->whereIn('id', $issues)->update(['recurrence_of_issue_id' => null]);

        $count = [];
        $count['activity_logs'] = DB::table('activity_logs')->where('company_id', $c)->delete();
        $count['notifications'] = DB::table('notifications')->whereIn('notifiable_id', $users)->where('notifiable_type', 'like', '%User')->delete();
        $count['feedback'] = DB::table('feedback')->where(fn ($q) => $q->whereIn('user_id', $users)->orWhereIn('issue_id', $issues)->orWhereIn('outcome_id', $outcomes))->delete();
        $count['task_comments'] = DB::table('task_comments')->whereIn('task_id', $tasks)->delete();
        $count['tasks'] = DB::table('tasks')->whereIn('department_id', $departments)->delete();
        $count['task_drafts'] = DB::table('task_drafts')->whereIn('meeting_id', $meetings)->delete();
        $count['meeting_issues'] = DB::table('meeting_issues')->whereIn('meeting_id', $meetings)->delete();
        $count['meetings'] = DB::table('meetings')->whereIn('department_id', $departments)->delete();
        $count['outcomes'] = DB::table('outcomes')->whereIn('id', $outcomes)->delete();
        $count['brief_items'] = DB::table('brief_items')->whereIn('brief_id', $briefs)->delete();
        $count['briefs'] = DB::table('briefs')->where('company_id', $c)->delete();
        $count['issues'] = DB::table('issues')->whereIn('department_id', $departments)->delete();
        $count['rule_evaluations'] = DB::table('rule_evaluations')->whereIn('department_id', $departments)->delete();
        $count['sales_records'] = DB::table('sales_records')->whereIn('department_id', $departments)->delete();
        $count['import_errors'] = DB::table('import_errors')->whereIn('import_id', $imports)->delete();
        $count['imports'] = DB::table('imports')->whereIn('department_id', $departments)->delete();
        $count['business_rules'] = DB::table('business_rules')->whereIn('department_id', $departments)->delete();
        $count['users'] = DB::table('users')->where('company_id', $c)->delete();
        $count['departments'] = DB::table('departments')->where('company_id', $c)->delete();
        $count['companies'] = DB::table('companies')->where('id', $c)->delete();

        return $count;
    }

    /**
     * @param  list<string>  $paths
     * @return array{0: int, 1: list<string>}
     */
    private function deleteFiles(array $paths): array
    {
        $disk = Storage::disk('local');
        $deleted = 0;
        $failed = [];

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $disk->delete($path) && ! $disk->exists($path) ? $deleted++ : $failed[] = $path;
        }

        return [$deleted, $failed];
    }
}
