<?php

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Actions\Intelligence\SeedDefaultRules;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ResolutionType;
use App\Enums\RuleKey;
use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Department;
use App\Models\Import;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\Intelligence\MetricCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
| Every feature test runs on the PostgreSQL test database (guarded in Tests\TestCase) and is rolled back.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Fictional fixture: one company with a Sales department (KL, PG, JB) and a second, test-only
 * Support department, with users in every role.
 */
function identity(): stdClass
{
    $f = new stdClass;

    $f->company = Company::factory()->create(['name' => 'Fixture Trading Sdn Bhd']);
    $f->sales = Department::factory()->create([
        'company_id' => $f->company->id,
        'name' => 'Sales',
        'slug' => 'sales',
        'settings' => ['expected_branches' => ['KL', 'PG', 'JB']],
    ]);
    $f->support = Department::factory()->create([
        'company_id' => $f->company->id,
        'name' => 'Support',
        'slug' => 'support',
    ]);

    $f->owner = User::factory()->owner()->create(['company_id' => $f->company->id, 'name' => 'Olivia Owner', 'email' => 'owner@fixture.test']);
    $f->manager = User::factory()->manager($f->sales)->create(['name' => 'Marcus Manager', 'email' => 'manager@fixture.test', 'manager_id' => $f->owner->id]);
    $f->staff = User::factory()->staff($f->sales)->create(['name' => 'Sara Staff', 'email' => 'staff@fixture.test', 'manager_id' => $f->manager->id, 'job_title' => 'Sales Executive (KL)']);
    $f->inactive = User::factory()->staff($f->sales)->inactive()->create(['name' => 'Ivan Inactive', 'email' => 'inactive@fixture.test', 'manager_id' => $f->manager->id]);
    $f->otherManager = User::factory()->manager($f->support)->create(['name' => 'Mona Manager', 'email' => 'other-manager@fixture.test']);
    $f->otherStaff = User::factory()->staff($f->support)->create(['name' => 'Omar Other', 'email' => 'other-staff@fixture.test', 'manager_id' => $f->otherManager->id]);
    $f->noDepartment = User::factory()->create(['company_id' => $f->company->id, 'role' => 'manager', 'department_id' => null, 'email' => 'nodept@fixture.test']);

    return $f;
}

/**
 * An uploaded CSV with the given content (fictional test data only).
 */
function csvUpload(string $content, string $name = 'sales.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * One of the committed demo fixtures as an upload.
 */
function fixtureUpload(string $fixture): UploadedFile
{
    return new UploadedFile(base_path("database/demo/{$fixture}.csv"), "{$fixture}.csv", 'text/csv', null, true);
}

/**
 * Fake the private disk and set the demo clock ("YYYY-MM-DD HH:MM", company timezone).
 */
function setClock(string $dateTime): void
{
    Storage::fake('local');
    config(['demo.mode' => true]);
    Storage::disk('local')->put(config('demo.clock_file'), $dateTime);
}

/**
 * Upload, confirm and process a demo fixture through the real actions (the queue is `sync` in tests).
 */
function importFixture(User $user, string $fixture, ?string $clock = null): Import
{
    if ($clock !== null) {
        setClock($clock);
    }

    $import = app(CreateImportFromUpload::class)->handle($user, fixtureUpload($fixture));
    app(ConfirmImport::class)->handle($import);

    return $import->refresh();
}

/**
 * The sum of active revenue as a decimal string (bcmath, no floats).
 */
function activeRevenue(?int $departmentId = null): string
{
    $query = SalesRecord::query()->where('is_active', true);
    $departmentId && $query->where('department_id', $departmentId);

    $sum = '0';
    foreach ($query->pluck('revenue') as $revenue) {
        $sum = bcadd($sum, (string) $revenue, 2);
    }

    return $sum;
}

/**
 * Seed the fictional Meridian demo identity (company, Sales department, users, rules with high_drop_pct 15)
 * and return its Owner. Requires nothing else: imports run through the real workflow.
 */
function demoOwner(): User
{
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123']);
    test()->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->assertSuccessful();

    return User::where('email', 'owner@meridian.demo')->firstOrFail();
}

function demoDepartment(): Department
{
    return Department::where('slug', 'sales')->firstOrFail();
}

/**
 * The newest stored evaluation of a rule (by id) at or after the given id.
 */
function latestEvaluation(string $ruleKey, int $afterId = 0): ?RuleEvaluation
{
    return RuleEvaluation::query()
        ->whereHas('rule', fn ($query) => $query->where('key', $ruleKey))
        ->where('id', '>', $afterId)
        ->orderByDesc('id')
        ->first();
}

function lastEvaluationId(): int
{
    return (int) RuleEvaluation::max('id');
}

/**
 * @return array<string, mixed>
 */
function expectedFixtures(): array
{
    return json_decode((string) file_get_contents(base_path('database/demo/expected.json')), true, 512, JSON_THROW_ON_ERROR)['fixtures'];
}

/**
 * Assert one stored evaluation against a hand-written expectation of database/demo/expected.json.
 *
 * @param  array<string, mixed>  $expected
 */
function assertMatchesExpected(?RuleEvaluation $evaluation, array $expected): void
{
    expect($evaluation)->not->toBeNull("no evaluation stored for {$expected['rule']}");
    $metrics = app(MetricCalculator::class);

    expect($evaluation->result->value)->toBe($expected['result'], "{$expected['rule']} result")
        ->and($evaluation->severity?->value)->toBe($expected['severity'], "{$expected['rule']} severity")
        ->and($evaluation->insufficient_reason?->value)->toBe($expected['reason'], "{$expected['rule']} reason");

    if (isset($expected['presentDueBranchDays'])) {
        expect((int) $evaluation->current_value)->toBe($expected['presentDueBranchDays'], "{$expected['rule']} present")
            ->and((int) $evaluation->comparison_value)->toBe($expected['expectedDueBranchDays'], "{$expected['rule']} expected");
    } elseif (isset($expected['current'])) {
        expect($evaluation->current_value)->toBe($expected['current'])
            ->and($evaluation->comparison_value)->toBe($expected['comparison'])
            ->and($evaluation->difference_value)->toBe($expected['difference']);
    }

    if (isset($expected['differencePct'])) {
        expect($metrics->display2((string) $evaluation->difference_pct))->toBe($expected['differencePct'], "{$expected['rule']} percentage");
    }
}

/**
 * A 6-day, 3-branch week (18 rows) with the given revenue and target per row, as CSV text.
 */
function weekCsv(string $monday, string $revenue, string $target = '5000.00'): string
{
    $rows = '';
    for ($day = 0; $day < 6; $day++) {
        $date = CarbonImmutable::parse($monday, 'UTC')->addDays($day)->format('Y-m-d');
        foreach (['KL', 'PG', 'JB'] as $branch) {
            $rows .= "{$date},{$branch},{$revenue},{$target},\n";
        }
    }

    return $rows;
}

/**
 * Import CSV text through the real workflow as the given user (the queue is `sync`, so evaluation runs too).
 */
function importCsv(User $user, string $body): Import
{
    $import = app(CreateImportFromUpload::class)->handle($user, csvUpload("sales_date,branch_code,revenue,target_revenue,order_count\n".$body));
    app(ConfirmImport::class)->handle($import);

    return $import->refresh();
}

/**
 * An issue row for lifecycle and authorization tests, with the evaluation it points at, without importing data.
 */
function fakeIssue(
    Department $department,
    IssueStatus $status = IssueStatus::Open,
    IssueSeverity $severity = IssueSeverity::High,
    RuleKey $key = RuleKey::WeeklyRevenueDecline,
): Issue {
    app(SeedDefaultRules::class)->handle($department);
    $rule = BusinessRule::where('department_id', $department->id)->where('key', $key->value)->firstOrFail();
    $now = now()->format('Y-m-d H:i:s');

    $evaluationId = DB::table('rule_evaluations')->insertGetId([
        'business_rule_id' => $rule->id, 'department_id' => $department->id, 'period_start' => '2026-09-21', 'period_end' => '2026-09-27',
        'metric_name' => $key->metricName(), 'current_value' => '124000.00', 'comparison_value' => '155000.00', 'difference_value' => '-31000.00',
        'difference_pct' => '-20.0000', 'result' => 'breached', 'severity' => $severity->value, 'as_of_date' => '2026-09-26', 'last_due_date' => '2026-09-26',
        'evidence' => json_encode(['calendar' => ['timezone' => 'Asia/Kuala_Lumpur'], 'thresholdUsed' => '15.00', 'periods' => [], 'coverage' => [], 'contributingImportIds' => []]),
        'evaluated_at' => $now, 'run_key' => hash('sha256', uniqid('', true)), 'created_at' => $now, 'updated_at' => $now,
    ]);

    $resolved = $status === IssueStatus::Resolved;

    return Issue::create([
        'department_id' => $department->id, 'business_rule_id' => $rule->id, 'scope_key' => 'department', 'title' => $key->issueTitle(),
        'severity' => $severity, 'status' => $status, 'first_detected_at' => $now, 'last_detected_at' => $now, 'detection_count' => 1,
        'period_start' => '2026-09-21', 'period_end' => '2026-09-27', 'baseline_evaluation_id' => $evaluationId, 'latest_evaluation_id' => $evaluationId,
        'status_history' => [['from' => null, 'to' => 'open', 'at' => $now, 'by_user_id' => null, 'reason' => null, 'trigger' => 'created']],
        'resolved_at' => $resolved ? $now : null,
        'resolution_type' => $resolved ? ResolutionType::Manual : null,
        'resolution_note' => $resolved ? 'Resolved in a test.' : null,
    ])->refresh();
}

/** History at 21 Sep, then the week 8 decline at 28 Sep: a high decline issue and a medium target issue. */
function declineTimeline(): User
{
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');
    importFixture($owner, 'week8-decline');

    return $owner;
}

function declineIssue(): Issue
{
    return Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->firstOrFail();
}

/**
 * The demo roster of the Sales department with the two Daniels (ambiguous first name) and the Owner outside the list.
 *
 * @return object{company: Company, sales: Department, owner: User, manager: User, priya: User, farid: User, danielTan: User, danielLee: User}
 */
function meetingRoster(): stdClass
{
    $f = new stdClass;
    $f->company = Company::factory()->create(['name' => 'Meridian Test Sdn Bhd', 'timezone' => 'Asia/Kuala_Lumpur', 'trading_days' => [1, 2, 3, 4, 5, 6]]);
    $f->sales = Department::factory()->create(['company_id' => $f->company->id, 'name' => 'Sales', 'slug' => 'sales', 'settings' => ['expected_branches' => ['KL', 'PG', 'JB']]]);
    $f->owner = User::factory()->owner()->create(['company_id' => $f->company->id, 'name' => 'Lim Wei Han', 'email' => 'weihan@m.test']);
    $f->manager = User::factory()->manager($f->sales)->create(['name' => 'Nurul Aisyah', 'email' => 'aisyah@m.test', 'manager_id' => $f->owner->id]);
    $f->priya = User::factory()->staff($f->sales)->create(['name' => 'Priya Nair', 'email' => 'priya@m.test', 'manager_id' => $f->manager->id]);
    $f->farid = User::factory()->staff($f->sales)->create(['name' => 'Farid Ismail', 'email' => 'farid@m.test', 'manager_id' => $f->manager->id]);
    $f->danielTan = User::factory()->staff($f->sales)->create(['name' => 'Daniel Tan', 'email' => 'dtan@m.test', 'manager_id' => $f->manager->id]);
    $f->danielLee = User::factory()->staff($f->sales)->create(['name' => 'Daniel Lee', 'email' => 'dlee@m.test', 'manager_id' => $f->manager->id]);

    return $f;
}

/**
 * A meeting of the roster's Sales department in a given state (no files, no jobs).
 *
 * @param  array<string, mixed>  $attributes
 */
function makeMeeting(stdClass $f, array $attributes = []): Meeting
{
    return Meeting::create($attributes + [
        'department_id' => $f->sales->id,
        'created_by' => $f->manager->id,
        'title' => 'Sales recovery huddle',
        'meeting_date' => '2026-09-28',
    ]);
}

/** The fictional demo meeting transcript (docs/demo-scenario.md section 4). */
function demoTranscript(): string
{
    return trim((string) file_get_contents(base_path('database/demo/meeting-transcript.txt')));
}

/** Provider configuration for meeting tests (fake values; every call goes through Http::fake). */
function configureMeetingAi(): void
{
    config([
        'ai.base_url' => 'https://ai.example.test/v1', 'ai.api_key' => 'test-key-not-real',
        'ai.text_model' => 'text-model-x', 'ai.transcription_model' => 'transcribe-model-x',
    ]);
}

/**
 * An OpenAI-style chat answer carrying the given extraction JSON.
 *
 * @param  array<string, mixed>  $extraction
 */
function extractionReply(array $extraction): array
{
    return ['choices' => [['message' => ['content' => json_encode($extraction)]]]];
}

/**
 * What a faithful model returns for the demo transcript, with deliberately messy extras the server must handle.
 *
 * @return array<string, mixed>
 */
function demoExtraction(): array
{
    return [
        'decisions' => [
            ['summary' => 'Prioritise reactivating the top lapsed Penang accounts.', 'excerpt' => 'Decision one: we will prioritise reactivating the top lapsed Penang accounts.'],
            ['summary' => 'Run a limited pricing promotion trial in Johor Bahru.', 'excerpt' => 'Decision two: we will run a limited pricing promotion trial in Johor Bahru.'],
        ],
        'task_drafts' => [
            ['title' => 'Call the ten biggest lapsed Penang accounts', 'description' => '', 'assignee_name' => 'Priya', 'assignee_id' => null, 'deadline_phrase' => 'by Friday', 'excerpt' => 'Priya, please call the ten biggest lapsed Penang accounts by Friday.'],
            ['title' => 'Prepare the JB promo proposal', 'description' => '', 'assignee_name' => 'Farid', 'assignee_id' => null, 'deadline_phrase' => 'by next Wednesday', 'excerpt' => 'Farid, put together the JB promo proposal by next Wednesday.'],
            ['title' => 'Sort out the KL weekend display', 'description' => '', 'assignee_name' => 'Daniel', 'assignee_id' => null, 'deadline_phrase' => null, 'excerpt' => 'Daniel will sort out the KL weekend display.'],
            ['title' => 'Check why the Saturday stock was short', 'description' => '', 'assignee_name' => null, 'assignee_id' => null, 'deadline_phrase' => null, 'excerpt' => 'Someone should check why the Saturday stock was short.'],
        ],
    ];
}

/** A meeting that already holds the demo transcript and is ready for extraction. */
function transcribedMeeting(stdClass $f, ?string $transcript = null): Meeting
{
    return makeMeeting($f, ['status' => 'transcribed', 'transcript' => $transcript ?? demoTranscript(), 'transcript_source' => 'manual']);
}
