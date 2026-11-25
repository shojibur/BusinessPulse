<?php

use App\Enums\BriefSource;
use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Models\Company;
use App\Models\User;
use App\Services\AI\BriefContext;
use App\Services\AI\BriefPresenter;
use App\Services\AI\BriefSummaryComposer;
use App\Services\AI\NumberAllowListChecker;
use App\Services\Imports\ReportingCoverage;
use App\Services\Intelligence\IssueEvidence;
use Illuminate\Support\Facades\Http;

// S05 PoV Core test group (design "Testing Strategy"). No test calls the external provider.

beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.api_key' => null, 'ai.base_url' => null, 'ai.text_model' => null, 'ai.speech_model' => null, 'ai.speech_voice' => null]);
});

test('number allow-list rejects numbers not in evidence', function () {
    declineTimeline();
    $evidence = IssueEvidence::forIssue(declineIssue())->toArray();
    $check = app(NumberAllowListChecker::class);

    expect($check->passes('Revenue fell to RM 99,999.00 last week.', $evidence))->toBeFalse()
        ->and($check->passes('Revenue fell to RM 124,000.00 from RM 155,000.00, a change of -20.00%.', $evidence))->toBeTrue()
        ->and($check->passes('Revenue fell to 124,000 from 155,000, down 20%.', $evidence))->toBeTrue()
        ->and($check->passes('There were 7 branches affected.', $evidence))->toBeFalse();
});

test('fallback summary uses only evidence figures', function () {
    declineTimeline();
    $issue = declineIssue();
    $evidence = IssueEvidence::forIssue($issue)->toArray();
    $label = BriefSummaryComposer::range($issue->period_start, $issue->period_end);
    $sentence = app(BriefPresenter::class)->figuresFromEvidence($evidence, $label);

    expect($sentence)->toContain('RM 124,000.00')->toContain('RM 155,000.00')->toContain('-20.00%')
        ->and(app(NumberAllowListChecker::class)->passes($sentence, $evidence))->toBeTrue();
});

test('the stale summary template states the overdue range and branch-days from freshness only', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');
    $department = demoDepartment();
    $coverage = app(ReportingCoverage::class);
    $context = new BriefContext($coverage->latestClosedWeek($department), $coverage->freshness($department), ['status' => 'current', 'last_evaluated_at' => null, 'failed_rules' => 0], 'Asia/Kuala_Lumpur');
    $composed = app(BriefSummaryComposer::class)->compose(0, $context);

    expect($composed['summary'])->toContain('Sales for 21–26 Sep not yet received: 18 branch-days overdue.')
        ->and($composed['headline'])->toBe('No active issues were found.');
});

test('brief item evidence is copied from rule evaluations and later evaluations do not change it', function () {
    $owner = declineTimeline();

    $this->actingAs($owner)->post('/briefs')->assertRedirect();
    $brief = Brief::firstOrFail();
    $brief->load('items');

    expect($brief->status)->toBe(BriefStatus::Completed)->and($brief->source)->toBe(BriefSource::Fallback)
        ->and($brief->error_message)->toBeNull()
        ->and($brief->items)->toHaveCount(2)
        ->and($brief->items[0]->severity)->toBe('high')->and($brief->items[1]->severity)->toBe('medium');

    $issue = declineIssue();
    $stored = $brief->items[0]->evidence;
    expect($stored)->toEqual(IssueEvidence::forIssue($issue)->toArray())
        ->and($brief->items[0]->source_ref)->toBe("issue:{$issue->id}:evaluation:{$issue->latest_evaluation_id}");

    // A later evaluation (a re-run after a correction) never rewrites the stored item.
    importFixture($owner, 'week8-correction');
    expect($brief->items()->first()->evidence)->toEqual($stored);
    Http::assertNothingSent();
});

test('staff cannot generate a brief', function () {
    $f = identity();

    $this->actingAs($f->staff)->post('/briefs')->assertForbidden();
    $this->actingAs($f->staff)->get('/briefs')->assertForbidden();
    expect(Brief::count())->toBe(0);
});

test('audio route denies unauthorized users', function () {
    $f = identity();
    $brief = Brief::create(['company_id' => $f->company->id, 'status' => 'completed', 'audio_status' => 'ready', 'audio_path' => 'briefs/audio/x.mp3']);

    $otherCompany = Company::factory()->create();
    $otherOwner = User::factory()->owner()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($f->staff)->get("/briefs/{$brief->id}/audio")->assertForbidden();
    $this->actingAs($otherOwner)->get("/briefs/{$brief->id}/audio")->assertNotFound();
    auth()->logout();
    $this->get("/briefs/{$brief->id}/audio")->assertRedirect('/login');
});
