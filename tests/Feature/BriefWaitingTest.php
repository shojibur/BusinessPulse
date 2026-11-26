<?php

use App\Enums\BriefStatus;
use App\Models\Brief;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.base_url' => null, 'ai.api_key' => null, 'ai.text_model' => null, 'ai.text_provider' => 'openai', 'ai.anthropic_api_key' => null]);
    $this->f = meetingRoster();
});

function queuedBrief(int $companyId, int $userId, int $secondsOld): Brief
{
    $brief = Brief::query()->create(['company_id' => $companyId, 'requested_by' => $userId, 'status' => BriefStatus::Queued]);
    DB::table('briefs')->where('id', $brief->id)->update(['updated_at' => now()->subSeconds($secondsOld)]);

    return $brief;
}

test('a brief that has waited over 45 seconds is flagged as stalled, a fresh one is not', function () {
    $fresh = queuedBrief($this->f->company->id, $this->f->owner->id, 5);
    $old = queuedBrief($this->f->company->id, $this->f->owner->id, 120);

    $this->actingAs($this->f->owner)->get("/briefs/{$fresh->id}")->assertInertia(fn ($p) => $p->where('brief.status', 'queued')->where('brief.stalled', false));
    $this->actingAs($this->f->owner)->get("/briefs/{$old->id}")->assertInertia(fn ($p) => $p->where('brief.stalled', true));
});

test('the brief pages say when no AI provider is configured, and stop saying it once one is', function () {
    $brief = queuedBrief($this->f->company->id, $this->f->owner->id, 5);

    $this->actingAs($this->f->owner)->get('/briefs')->assertInertia(fn ($p) => $p->where('ai.text', false));
    $this->actingAs($this->f->owner)->get("/briefs/{$brief->id}")->assertInertia(fn ($p) => $p->where('ai.text', false));

    config(['ai.base_url' => 'https://ai.example.test/v1', 'ai.api_key' => 'k', 'ai.text_model' => 'm']);
    $this->actingAs($this->f->owner)->get('/briefs')->assertInertia(fn ($p) => $p->where('ai.text', true));
});
