<?php

use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Models\Company;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('the dashboard shows the latest week by default and an earlier closed week when chosen', function () {
    $owner = declineTimeline();

    $this->actingAs($owner)->get('/today')->assertInertia(fn ($p) => $p
        ->where('data.viewingWeek', null)->where('data.weekOptions.0.latest', true)->where('data.weekOptions.0.value', '2026-09-21')
        ->where('data.weekOptions.1.value', '2026-09-14')->where('data.revenue.display', 'RM 124,000.00'));

    // Any day of an earlier week selects that week (normalised to its Monday); its own figures are shown, never zeros.
    $this->actingAs($owner)->get('/today?week=2026-09-16')->assertInertia(fn ($p) => $p
        ->where('data.viewingWeek', '2026-09-14')->where('data.revenue.display', 'RM 155,000.00')
        ->where('data.revenue.period_start', '2026-09-14')->where('data.change.value', '0.65')
        // Issues and tasks are always today's status.
        ->where('data.openIssueCount', 2));
});

test('an invalid, future or unknown week falls back to the latest week', function (string $week) {
    $owner = declineTimeline();

    $this->actingAs($owner)->get("/today?week={$week}")->assertOk()->assertInertia(fn ($p) => $p
        ->where('data.viewingWeek', null)->where('data.revenue.display', 'RM 124,000.00'));
})->with(['junk', '2026-12-07', '1999-01-04', '2026-02-30']);

test('briefs can be filtered by the day their figures describe', function () {
    $f = meetingRoster();
    foreach (['2026-09-10', '2026-09-20', '2026-09-28'] as $i => $day) {
        $brief = Brief::query()->create(['company_id' => $f->company->id, 'requested_by' => $f->owner->id, 'status' => BriefStatus::Completed, 'as_of_date' => $day, 'headline' => "Brief {$day}"]);
        DB::table('briefs')->where('id', $brief->id)->update(['created_at' => now()->subDays(10 - $i)]);
    }

    $this->actingAs($f->owner)->get('/briefs')->assertInertia(fn ($p) => $p->has('briefs', 3)->where('filters.from', null));
    $this->actingAs($f->owner)->get('/briefs?from=2026-09-15&to=2026-09-25')->assertInertia(fn ($p) => $p
        ->has('briefs', 1)->where('briefs.0.as_of_date', '2026-09-20')->where('filters.from', '2026-09-15')->where('filters.to', '2026-09-25'));
    $this->actingAs($f->owner)->get('/briefs?from=2026-09-25')->assertInertia(fn ($p) => $p->has('briefs', 1)->where('briefs.0.as_of_date', '2026-09-28'));
    $this->actingAs($f->owner)->get('/briefs?from=2026-09-25&to=2026-09-01')->assertSessionHasErrors('to');
    $this->actingAs($f->owner)->get('/briefs?from=not-a-date')->assertSessionHasErrors('from');
});

test('each brief links to the earlier and later brief the user may see, and nobody sees another company', function () {
    $f = meetingRoster();
    $ids = [];
    foreach ([3, 2, 1] as $daysAgo) {
        $brief = Brief::query()->create(['company_id' => $f->company->id, 'requested_by' => $f->owner->id, 'status' => BriefStatus::Completed, 'as_of_date' => now()->subDays($daysAgo)->toDateString(), 'headline' => 'H']);
        DB::table('briefs')->where('id', $brief->id)->update(['created_at' => now()->subDays($daysAgo)]);
        $ids[] = $brief->id; // oldest first
    }
    $other = Brief::query()->create(['company_id' => Company::factory()->create()->id, 'requested_by' => User::factory()->create()->id, 'status' => BriefStatus::Completed, 'as_of_date' => now()->toDateString(), 'headline' => 'Other']);

    $this->actingAs($f->owner)->get("/briefs/{$ids[1]}")->assertInertia(fn ($p) => $p
        ->where('adjacent.previous.id', $ids[0])->where('adjacent.next.id', $ids[2]));
    $this->actingAs($f->owner)->get("/briefs/{$ids[0]}")->assertInertia(fn ($p) => $p->where('adjacent.previous', null)->where('adjacent.next.id', $ids[1]));
    $this->actingAs($f->owner)->get("/briefs/{$ids[2]}")->assertInertia(fn ($p) => $p->where('adjacent.next', null)); // never the other company's brief
    $this->actingAs($f->owner)->get("/briefs/{$other->id}")->assertNotFound();
});

test('the daily sales card shows the latest sales day by default and any chosen day by branch', function () {
    $owner = declineTimeline();

    $this->actingAs($owner)->get('/today')->assertInertia(fn ($p) => $p
        ->where('data.daily.date', '2026-09-26')->where('data.daily.default_date', '2026-09-26')->where('data.daily.status', 'complete')
        ->has('data.daily.branches', 3)->where('data.daily.total.reported_count', 3)->where('data.daily.total.expected_count', 3)
        ->where('data.daily.min_date', fn ($d) => is_string($d) && $d < '2026-09-01'));

    $this->actingAs($owner)->get('/today?day=2026-09-22')->assertInertia(fn ($p) => $p
        ->where('data.daily.date', '2026-09-22')->where('data.daily.status', 'complete')
        ->where('data.daily.branches.0.reported', true)->where('data.daily.total.revenue', fn ($r) => str_starts_with($r, 'RM ')));
});

test('the daily sales card explains a Sunday, a day not yet due, and ignores dates outside the data', function () {
    $owner = declineTimeline(); // the clock stands at Monday 28 Sep 2026

    $this->actingAs($owner)->get('/today?day=2026-09-27')->assertInertia(fn ($p) => $p
        ->where('data.daily.status', 'non_trading')->where('data.daily.total', null)->where('data.daily.message', 'This is not a trading day for the company.'));
    $this->actingAs($owner)->get('/today?day=2026-09-28')->assertInertia(fn ($p) => $p
        ->where('data.daily.status', 'not_due')->where('data.daily.total', null));

    foreach (['2026-12-31', '2001-01-01', 'junk', '2026-02-30'] as $bad) {
        $this->actingAs($owner)->get("/today?day={$bad}")->assertOk()->assertInertia(fn ($p) => $p->where('data.daily.date', '2026-09-26'));
    }
});

test('choosing a day and a week together keeps both', function () {
    $owner = declineTimeline();

    $this->actingAs($owner)->get('/today?week=2026-09-14&day=2026-09-16')->assertInertia(fn ($p) => $p
        ->where('data.viewingWeek', '2026-09-14')->where('data.daily.date', '2026-09-16'));
});

test('pressing Generate again with unchanged data opens the existing brief instead of writing a duplicate', function () {
    config(['ai.base_url' => null, 'ai.api_key' => null, 'ai.text_model' => null, 'ai.text_provider' => 'openai', 'ai.anthropic_api_key' => null]);
    $owner = declineTimeline();

    $this->actingAs($owner)->post('/briefs')->assertRedirect();
    $first = Brief::sole();
    expect($first->status->value)->toBe('completed')->and($first->source_fingerprint)->not->toBeNull();

    // Same data, same day, same AI: the existing brief is opened and nothing new is written.
    $this->actingAs($owner)->post('/briefs')->assertRedirect("/briefs/{$first->id}");
    expect(Brief::count())->toBe(1)->and(session('flash.message'))->toContain('already matches the current data');

    // An explicit request writes a new one.
    $this->actingAs($owner)->post('/briefs', ['force' => true])->assertRedirect();
    expect(Brief::count())->toBe(2);
});

test('a change in the data or in the configured AI gives a new brief', function () {
    config(['ai.base_url' => null, 'ai.api_key' => null, 'ai.text_model' => null, 'ai.text_provider' => 'openai', 'ai.anthropic_api_key' => null]);
    $owner = declineTimeline();
    $this->actingAs($owner)->post('/briefs');
    expect(Brief::count())->toBe(1);

    // The status of an open issue changes: a new brief is written.
    Issue::query()->firstOrFail()->forceFill(['status' => 'acknowledged'])->save();
    $this->actingAs($owner)->post('/briefs');
    expect(Brief::count())->toBe(2);

    // An AI provider is now configured: the same data would be written differently, so it is a new brief.
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"items": []}']]]])]);
    config(['ai.base_url' => 'https://ai.example.test/v1', 'ai.api_key' => 'k', 'ai.text_model' => 'm']);
    $this->actingAs($owner)->post('/briefs');
    expect(Brief::count())->toBe(3);
});
