<?php

use App\Enums\BriefSource;
use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Models\Company;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\User;
use App\Services\AI\BriefContext;
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

test('AI wording may not contain numbers, wrong directions, target confusion or causes', function () {
    declineTimeline();
    $decline = IssueEvidence::forIssue(declineIssue())->toArray();
    $shortfall = IssueEvidence::forIssue(Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'sales_target_shortfall'))->firstOrFail())->toArray();
    $check = app(NumberAllowListChecker::class);

    // Numbers that exist in the evidence are still refused: the application renders every figure itself.
    expect($check->passes('Revenue fell to RM 124,000.00 from RM 155,000.00, down 20.00%.', $decline))->toBeFalse()
        ->and($check->passes('Revenue fell to RM 99,999.00.', $decline))->toBeFalse()
        ->and($check->passes('Issue 7 on 2026 week 40 declined.', $decline))->toBeFalse()
        // Misleading wording with no numbers at all.
        ->and($check->passes('Revenue rose compared with the previous week.', $decline))->toBeFalse()
        ->and($check->passes('Sales grew and improved strongly.', $decline))->toBeFalse()
        ->and($check->passes('Revenue exceeded the weekly target.', $shortfall))->toBeFalse()
        ->and($check->passes('Revenue met the target this week.', $shortfall))->toBeFalse()
        ->and($check->passes('Revenue fell because the Penang accounts churned.', $decline))->toBeFalse()
        // Faithful wording.
        ->and($check->passes('Weekly revenue declined compared with the previous week.', $decline))->toBeTrue()
        ->and($check->passes('Revenue finished below the weekly target.', $shortfall))->toBeTrue();
});

test('the figures are rendered deterministically with value, direction, unit and the evaluated period', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['items' => [
        ['position' => 1, 'title' => 'Revenue fell', 'explanation' => 'Weekly revenue declined.'],
        ['position' => 2, 'title' => 'Target missed', 'explanation' => 'Revenue is below target.'],
    ]])]]]])]);
    config(['ai.api_key' => 'k', 'ai.base_url' => 'https://ai.example.test/v1', 'ai.text_model' => 'm']);

    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    $this->actingAs($owner)->get("/briefs/{$brief->id}")->assertInertia(fn ($page) => $page
        ->where('brief.items.0.figures', fn ($f) => str_contains($f, 'RM 124,000.00') && str_contains($f, 'RM 155,000.00') && str_contains($f, '-20.00%') && str_contains($f, '21–27 Sep 2026'))
        ->where('brief.items.1.figures', fn ($f) => str_contains($f, 'a target of RM 156,000.00') && str_contains($f, '79.49% of target')));
});

test('brief item period is the period of the evaluation shown, not the original issue period', function () {
    $owner = declineTimeline();
    $issue = declineIssue();
    // The issue was first detected for 21-27 Sep; a later breach for another week moves its latest evaluation.
    $latest = RuleEvaluation::find($issue->latest_evaluation_id);
    $latest->forceFill(['period_start' => '2026-10-05', 'period_end' => '2026-10-11'])->save();

    $this->actingAs($owner)->post('/briefs');
    $item = Brief::firstOrFail()->items()->where('issue_id', $issue->id)->firstOrFail();

    expect($item->period_label)->toBe('5–11 Oct 2026')->and($item->explanation)->toContain('5–11 Oct 2026');
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
