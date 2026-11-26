<?php

require_once __DIR__.'/helpers.php';

use App\Models\Company;
use App\Models\Department;
use App\Models\Feedback;
use App\Models\Outcome;
use App\Models\User;

beforeEach(function () {
    $this->x = monitoringScenario();
    importFixture($this->x->owner, 'week9-interim', '2026-10-05 09:00');
    $this->outcome = latestOutcome($this->x->decline);
    $this->manager = User::where('email', 'aisyah@meridian.demo')->first();
    $this->staff = User::where('email', 'priya@meridian.demo')->first();
});

test('owner and the department manager can open outcomes; staff are forbidden', function () {
    $this->actingAs($this->x->owner)->get('/outcomes')->assertOk();
    $this->actingAs($this->x->owner)->get("/outcomes/{$this->outcome->id}")->assertOk()->assertInertia(fn ($p) => $p->component('outcomes/Show')
        ->where('outcome.phase.usedForVerification', false)->where('outcome.values.baseline', '124000.00')->where('outcome.values.absoluteChange', '14000.00')
        ->where('outcome.heading', 'Observed change after actions'));
    $this->actingAs($this->manager)->get("/outcomes/{$this->outcome->id}")->assertOk();
    $this->actingAs($this->staff)->get('/outcomes')->assertForbidden();
    $this->actingAs($this->staff)->get("/outcomes/{$this->outcome->id}")->assertForbidden();
    $this->actingAs($this->staff)->post("/outcomes/{$this->outcome->id}/feedback", ['useful' => true])->assertForbidden();
});

test('foreign and missing outcomes are the same 404; other departments and companies see nothing', function () {
    $support = Department::factory()->create(['company_id' => $this->x->department->company_id, 'slug' => 'support']);
    $otherManager = User::factory()->manager($support)->create();
    $otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);

    $missing = $this->actingAs($otherManager)->get('/outcomes/9999999');
    $foreign = $this->actingAs($otherManager)->get("/outcomes/{$this->outcome->id}");
    $missing->assertNotFound();
    $foreign->assertNotFound();
    expect($foreign->viewData('page')['component'])->toBe($missing->viewData('page')['component']);
    $this->actingAs($otherOwner)->get("/outcomes/{$this->outcome->id}")->assertNotFound();
    $this->actingAs($otherManager)->post("/outcomes/{$this->outcome->id}/feedback", ['useful' => true])->assertNotFound();
    $this->actingAs($otherManager)->get('/outcomes')->assertInertia(fn ($p) => $p->where('pagination.total', 0));
    $this->actingAs($this->x->owner)->get('/outcomes')->assertInertia(fn ($p) => $p->where('pagination.total', 2)->has('filters')); // the latest outcome per issue (decline and target)
});

test('feedback is one replaceable vote per user and target, never changes a rule, and uses the route target', function () {
    $rules = DB::table('business_rules')->get()->map(fn ($r) => (array) $r)->all();
    $url = "/outcomes/{$this->outcome->id}/feedback";

    $this->actingAs($this->manager)->post($url, ['useful' => true, 'note' => 'Matches what we saw.', 'issue_id' => 999, 'user_id' => 1, 'outcome_id' => 5])->assertRedirect();
    $this->actingAs($this->manager)->post($url, ['useful' => false])->assertRedirect(); // replaces
    $this->actingAs($this->x->owner)->post($url, ['useful' => true])->assertRedirect();

    expect(Feedback::count())->toBe(2)->and(Feedback::where('user_id', $this->manager->id)->value('useful'))->toBeFalse()->and(Feedback::where('user_id', $this->manager->id)->value('note'))->toBeNull()
        ->and(Feedback::where('user_id', $this->manager->id)->value('outcome_id'))->toBe($this->outcome->id)->and(Feedback::whereNotNull('issue_id')->count())->toBe(0);
    expect(DB::table('business_rules')->get()->map(fn ($r) => (array) $r)->all())->toEqual($rules);

    $this->actingAs($this->manager)->post("/issues/{$this->x->decline->id}/feedback", ['useful' => true])->assertRedirect();
    $this->actingAs($this->manager)->post("/issues/{$this->x->decline->id}/feedback", ['useful' => false])->assertRedirect();
    expect(Feedback::whereNull('outcome_id')->count())->toBe(1)->and(Feedback::whereNull('outcome_id')->value('useful'))->toBeFalse();
    $this->actingAs($this->manager)->post($url, ['useful' => 'maybe'])->assertSessionHasErrors('useful');
    $this->actingAs($this->manager)->post($url, ['useful' => true, 'note' => str_repeat('a', 501)])->assertSessionHasErrors('note');
});

test('the database enforces exactly one feedback target and feedback survives a resolved issue', function () {
    dbRejects(fn () => DB::table('feedback')->insert(['user_id' => $this->manager->id, 'issue_id' => $this->x->decline->id, 'outcome_id' => $this->outcome->id, 'useful' => true, 'created_at' => now(), 'updated_at' => now()]));
    dbRejects(fn () => DB::table('feedback')->insert(['user_id' => $this->manager->id, 'useful' => true, 'created_at' => now(), 'updated_at' => now()]));

    importFixture($this->x->owner, 'week10-recovery', '2026-10-12 09:00');
    $this->actingAs($this->manager)->post("/issues/{$this->x->decline->id}/feedback", ['useful' => true])->assertRedirect();
    expect($this->x->decline->fresh()->status->value)->toBe('resolved')->and(Feedback::count())->toBe(1);
});
