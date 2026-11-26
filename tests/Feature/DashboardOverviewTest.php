<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.base_url' => null, 'ai.api_key' => null, 'ai.text_model' => null, 'ai.text_provider' => 'openai', 'ai.anthropic_api_key' => null]);
});

/** The demo company with history and week 8 imported through the real pipeline, plus three tasks in different states. */
function overviewScenario(): stdClass
{
    $x = new stdClass;
    $x->owner = demoOwner();
    $x->sales = demoDepartment();
    $x->manager = User::where('company_id', $x->owner->company_id)->where('role', 'manager')->firstOrFail();
    $x->priya = User::where('email', 'priya@meridian.demo')->firstOrFail();

    importFixture($x->owner, 'history', '2026-09-21 09:00');
    importFixture($x->owner, 'week8-decline', '2026-09-28 09:00');

    foreach (['pending', 'in_progress', 'completed'] as $status) {
        makeTask($x, $x->priya, ['status' => $status, 'title' => "Task {$status}"]);
    }

    return $x;
}

test('the overview lists urgent findings, task status shares and recent activity from real records', function () {
    $x = overviewScenario();

    $this->actingAs($x->owner)->get('/today')->assertInertia(fn ($p) => $p
        ->component('dashboard/Index')
        ->has('data.urgentIssues', 2)
        ->where('data.urgentIssues.0.severity', 'high')->where('data.urgentIssues.0.title', 'Weekly revenue decline')
        ->where('data.urgentIssues.0.detail', fn ($d) => str_starts_with((string) $d, '-20.00% vs the week before'))
        ->where('data.urgentIssues.1.severity', 'medium')
        ->where('data.taskStatus.pending', 1)->where('data.taskStatus.in_progress', 1)->where('data.taskStatus.completed', 1)
        ->where('data.taskStatus.total', 3)->where('data.taskStatus.percent.completed', 33)
        ->where('data.outcome', null)
        ->where('data.activity', fn ($rows) => count($rows) >= 1 && count($rows) <= 5 && $rows[0]['label'] !== '')
        ->where('ai.text', false));
});

test('a manager of another department sees none of the sales findings, tasks or activity', function () {
    $x = overviewScenario();
    $support = Department::factory()->create(['company_id' => $x->owner->company_id, 'slug' => 'support', 'name' => 'Support']);
    $other = User::factory()->manager($support)->create();

    $this->actingAs($other)->get('/today')->assertInertia(fn ($p) => $p
        ->has('data.urgentIssues', 0)->where('data.taskStatus.total', 0)->has('data.activity', 0)->where('data.outcome', null));
});

test('staff are sent to their tasks and no overview data is built for another company', function () {
    $x = overviewScenario();
    $stranger = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($x->priya)->get('/today')->assertRedirect('/my-tasks');
    $this->actingAs($stranger)->get('/today')->assertInertia(fn ($p) => $p->where('data', null));
});
