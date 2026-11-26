<?php

use App\Enums\AudioStatus;
use App\Enums\BriefSource;
use App\Enums\BriefStatus;
use App\Jobs\GenerateBriefAudioJob;
use App\Jobs\GenerateBriefJob;
use App\Models\Brief;
use App\Models\Department;
use App\Models\User;
use App\Services\AI\BriefGenerator;
use App\Services\AI\BriefPresenter;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Provider behaviour through Http::fake only: required tests never reach an external service.

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'ai.base_url' => 'https://ai.example.test/v1', 'ai.api_key' => 'test-key-not-real', 'ai.text_model' => 'text-model-x',
        'ai.speech_model' => 'speech-model-x', 'ai.speech_voice' => 'voice-x',
    ]);
});

function chatBody(array $items): array
{
    return ['choices' => [['message' => ['content' => json_encode(['items' => $items])]]]];
}

function validWording(): array
{
    return chatBody([
        ['position' => 1, 'title' => 'Revenue fell sharply', 'explanation' => 'Weekly revenue declined compared with the week before.'],
        ['position' => 2, 'title' => 'Target missed', 'explanation' => 'Revenue finished below the weekly target.'],
    ]);
}

function newBrief(?User $user = null): Brief
{
    return Brief::create(['company_id' => demoDepartment()->company_id, 'requested_by' => $user?->id, 'status' => 'queued']);
}

test('a valid provider answer produces an AI brief with validated wording', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(validWording())]);

    $this->actingAs($owner)->post('/briefs')->assertRedirect();
    $brief = Brief::firstOrFail()->load('items');

    expect($brief->source)->toBe(BriefSource::Ai)->and($brief->model)->toBe('text-model-x')->and($brief->error_message)->toBeNull()
        ->and($brief->items[0]->title)->toBe('Revenue fell sharply')
        ->and($brief->coverage_status->value)->toBe('sufficient')
        ->and($brief->input_snapshot['items'])->toHaveCount(2);

    // The key and the evidence bodies never reach the stored snapshot or error message.
    expect(json_encode($brief->input_snapshot))->not->toContain('test-key-not-real');
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer test-key-not-real') && $r['model'] === 'text-model-x');
});

test('an invented number triggers one corrective request, then the deterministic fallback', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(chatBody([
        ['position' => 1, 'title' => 'Revenue fell', 'explanation' => 'Revenue dropped to RM 99,999.00.'],
        ['position' => 2, 'title' => 'Target missed', 'explanation' => 'Revenue finished below the weekly target.'],
    ]))]);

    $this->actingAs($owner)->post('/briefs')->assertRedirect();
    $brief = Brief::firstOrFail()->load('items');

    Http::assertSentCount(2);
    expect($brief->status)->toBe(BriefStatus::Completed)->and($brief->source)->toBe(BriefSource::Fallback)
        ->and($brief->error_message)->toBe('invalid_response')
        ->and($brief->items[0]->explanation)->not->toContain('99,999');
});

test('a corrective request can repair the answer within the same attempt', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::sequence()
        ->push(chatBody([['position' => 1, 'title' => 'x', 'explanation' => 'Down 99.00%'], ['position' => 2, 'title' => 'y', 'explanation' => 'Short of target']]))
        ->push(validWording())]);

    $this->actingAs($owner)->post('/briefs')->assertRedirect();

    Http::assertSentCount(2);
    expect(Brief::firstOrFail()->source)->toBe(BriefSource::Ai);
});

test('a 4xx answer falls back immediately without retry', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(['error' => ['message' => 'secret detail']], 401)]);

    $this->actingAs($owner)->post('/briefs')->assertRedirect();
    $brief = Brief::firstOrFail();

    Http::assertSentCount(1);
    expect($brief->source)->toBe(BriefSource::Fallback)->and($brief->error_message)->toBe('client_error')
        ->and($brief->status)->toBe(BriefStatus::Completed);
});

test('recoverable provider failures are retried by the queue until the final attempt, then fall back', function (int|string $failure, string $code) {
    $owner = declineTimeline();
    Http::fake(['*' => $failure === 'timeout' ? fn () => throw new ConnectionException('timed out') : Http::response([], $failure)]);
    $brief = newBrief($owner);

    expect(fn () => app(BriefGenerator::class)->generate($brief, finalAttempt: false))->toThrow(RecoverableProviderException::class);
    expect($brief->fresh()->status)->toBe(BriefStatus::Queued);
    is_int($failure) && Http::assertSentCount(1); // one HTTP attempt per call: the client never retries

    app(BriefGenerator::class)->generate($brief, finalAttempt: true);
    $brief->refresh();
    expect($brief->source)->toBe(BriefSource::Fallback)->and($brief->error_message)->toBe($code)->and($brief->status)->toBe(BriefStatus::Completed);
})->with([
    'rate limit' => [429, 'rate_limited'],
    'server error' => [503, 'server_error'],
    'timeout' => ['timeout', 'timeout'],
]);

test('unparsable provider output falls back as invalid_response', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'not json']]]])]);

    $this->actingAs($owner)->post('/briefs');
    expect(Brief::firstOrFail()->error_message)->toBe('invalid_response');
});

test('the job queue settings follow the retry layering', function () {
    $job = new GenerateBriefJob(1, 1);
    $audio = new GenerateBriefAudioJob(1);

    expect($job->queue)->toBe('default')->and($job->tries)->toBe(3)->and($job->timeout)->toBe(90)->and($job->backoff())->toBe([10, 30, 60])
        ->and($audio->queue)->toBe('default')->and($audio->timeout)->toBe(90)->and($audio->tries)->toBe(3)->and($audio->backoff())->toBe([10, 30, 60])
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(90);
});

test('generation is idempotent: a repeated run leaves one set of items', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(validWording())]);
    $brief = newBrief($owner);

    app(BriefGenerator::class)->generate($brief, true);
    app(BriefGenerator::class)->generate($brief->fresh(), true);

    expect($brief->items()->count())->toBe(2);
});

test('a completed brief is not generated again by a duplicate job delivery', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(validWording())]);
    $this->actingAs($owner)->post('/briefs');
    Http::assertSentCount(1);

    dispatch_sync(new GenerateBriefJob(Brief::first()->id, Brief::first()->company_id));
    Http::assertSentCount(1);
});

test('no provider call is made without configuration or without findings', function () {
    $owner = declineTimeline();
    config(['ai.api_key' => null]);

    $this->actingAs($owner)->post('/briefs');
    expect(Brief::firstOrFail()->source)->toBe(BriefSource::Fallback)->and(Brief::first()->error_message)->toBeNull();
    Http::assertNothingSent();
});

test('a brief with no issues is deterministic, marked stale and states the overdue range', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');

    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    expect($brief->items()->count())->toBe(0)->and($brief->coverage_status->value)->toBe('stale')
        ->and($brief->source)->toBe(BriefSource::Fallback)
        ->and($brief->summary_text)->toContain('Sales for 21–26 Sep not yet received: 18 branch-days overdue.');
    Http::assertNothingSent();
});

test('a partial evaluation blocks the normal brief and the AI wording', function () {
    $owner = declineTimeline();
    Department::query()->update(['settings' => DB::raw("jsonb_set(settings, '{intelligence,last_run_status}', '\"partial\"')")]);
    Department::query()->update(['settings' => DB::raw("jsonb_set(settings, '{intelligence,last_run_failed_rules}', '1')")]);

    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    Http::assertNothingSent();
    expect($brief->source)->toBe(BriefSource::Fallback)->and($brief->error_message)->toBe('evaluation_partial')
        ->and($brief->coverage_status->value)->toBe('partial')
        ->and($brief->summary_text)->toContain('could not evaluate 1 rule(s)');
});

test('only one brief can be in progress per company and Manager briefs are scoped to their department', function () {
    $owner = declineTimeline();
    Brief::create(['company_id' => $owner->company_id, 'status' => 'queued']);

    $this->actingAs($owner)->post('/briefs')->assertSessionHasErrors('brief');
    expect(Brief::count())->toBe(1);
});

test('audio is generated into private storage and streamed only to authorized users', function () {
    $owner = declineTimeline();
    Http::fake(['*/chat/completions' => Http::response(validWording()), '*/audio/speech' => Http::response('ID3-fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg'])]);
    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    $this->actingAs($owner)->post("/briefs/{$brief->id}/audio")->assertRedirect();
    $brief->refresh();

    expect($brief->audio_status)->toBe(AudioStatus::Ready)->and($brief->audio_path)->toStartWith('briefs/audio/')->and($brief->audio_model)->toBe('speech-model-x');
    Storage::disk('local')->assertExists($brief->audio_path);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/audio/speech') && $r['input'] === app(BriefPresenter::class)->spokenText($brief) && $r['voice'] === 'voice-x');

    $response = $this->actingAs($owner)->get("/briefs/{$brief->id}/audio");
    $response->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    expect($response->baseResponse->getFile()->getPathname())->toContain('briefs/audio/');
});

test('audio reports an unconfigured voice and a provider failure without faking success', function () {
    $owner = declineTimeline();
    Http::fake(['*/chat/completions' => Http::response(validWording()), '*/audio/speech' => Http::response([], 401)]);
    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    $this->actingAs($owner)->post("/briefs/{$brief->id}/audio");
    expect($brief->fresh()->audio_status)->toBe(AudioStatus::Failed)->and($brief->fresh()->audio_path)->toBeNull();
    $this->actingAs($owner)->get("/briefs/{$brief->id}/audio")->assertNotFound();

    config(['ai.speech_model' => null]);
    $this->actingAs($owner)->post("/briefs/{$brief->id}/audio")->assertSessionHasErrors(['audio' => 'Voice is not configured.']);
});

test('provider logs carry only the request type, brief id, status and duration', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(validWording())]);
    Log::spy();

    $this->actingAs($owner)->post('/briefs');

    Log::shouldHaveReceived('info')->with('ai_request', Mockery::on(fn ($c) => array_keys($c) === ['type', 'brief_id', 'status', 'duration_ms']))->atLeast()->once();
});

test('the brief page exposes explicit props and hides other companies', function () {
    $owner = declineTimeline();
    Http::fake(['*' => Http::response(validWording())]);
    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    $this->actingAs($owner)->get("/briefs/{$brief->id}")->assertInertia(fn ($page) => $page
        ->component('briefs/Show')->where('brief.source', 'ai')->where('brief.notice', 'AI generated')->has('brief.items', 2)
        ->missing('brief.input_snapshot')->where('audio.configured', true));
    $this->actingAs($owner)->get('/briefs')->assertInertia(fn ($page) => $page->component('briefs/Index')->has('briefs', 1));
});

test('a manager only sees, generates, retries and streams briefs of the own department', function () {
    $f = identity();
    $salesBrief = Brief::create(['company_id' => $f->company->id, 'department_id' => $f->sales->id, 'status' => 'completed', 'audio_status' => 'ready', 'audio_path' => 'briefs/audio/s.mp3']);
    $ownerBrief = Brief::create(['company_id' => $f->company->id, 'department_id' => null, 'status' => 'failed']);
    $supportBrief = Brief::create(['company_id' => $f->company->id, 'department_id' => $f->support->id, 'status' => 'failed']);
    Storage::disk('local')->put('briefs/audio/s.mp3', 'ID3xx');

    // The Sales Manager: own department only (an Owner's company-wide brief and another department's brief are 404).
    $this->actingAs($f->manager)->get("/briefs/{$salesBrief->id}")->assertOk();
    $this->actingAs($f->manager)->get("/briefs/{$salesBrief->id}/audio")->assertOk();
    foreach ([$ownerBrief, $supportBrief] as $other) {
        $this->actingAs($f->manager)->get("/briefs/{$other->id}")->assertNotFound();
        $this->actingAs($f->manager)->get("/briefs/{$other->id}/audio")->assertNotFound();
        $this->actingAs($f->manager)->post("/briefs/{$other->id}/retry")->assertNotFound();
        $this->actingAs($f->manager)->post("/briefs/{$other->id}/audio")->assertNotFound();
    }
    $this->actingAs($f->manager)->get('/briefs')->assertInertia(fn ($page) => $page->has('briefs', 1)->where('briefs.0.id', $salesBrief->id));

    // The other department's Manager cannot see the Sales brief; the Owner sees everything of the company.
    $this->actingAs($f->otherManager)->get("/briefs/{$salesBrief->id}")->assertNotFound();
    $this->actingAs($f->otherManager)->get("/briefs/{$salesBrief->id}/audio")->assertNotFound();
    $this->actingAs($f->owner)->get('/briefs')->assertInertia(fn ($page) => $page->has('briefs', 3));
    $this->actingAs($f->owner)->get("/briefs/{$supportBrief->id}")->assertOk();
});

test('a manager brief is stored with the manager department and only cites its issues', function () {
    config(['ai.api_key' => null]);
    $f = identity();
    $salesIssue = fakeIssue($f->sales);
    $supportIssue = fakeIssue($f->support);

    $this->actingAs($f->manager)->post('/briefs')->assertRedirect();
    $managerBrief = Brief::firstOrFail();
    expect($managerBrief->department_id)->toBe($f->sales->id)
        ->and($managerBrief->items()->pluck('issue_id')->all())->toBe([$salesIssue->id]);

    $managerBrief->update(['status' => 'completed']);
    $this->actingAs($f->owner)->post('/briefs')->assertRedirect();
    $ownerBrief = Brief::latest('id')->firstOrFail();
    expect($ownerBrief->department_id)->toBeNull()->and($ownerBrief->items()->pluck('issue_id')->sort()->values()->all())->toBe([$salesIssue->id, $supportIssue->id]);
});

test('audio is only marked ready after a valid file is written, and a vanished file becomes a visible failure', function () {
    $owner = declineTimeline();
    Http::fake(['*/chat/completions' => Http::response(validWording()), '*/audio/speech' => Http::response('{"error":"nope"}', 200)]);
    $this->actingAs($owner)->post('/briefs');
    $brief = Brief::firstOrFail();

    // A 200 answer that is not audio never becomes "ready" and leaves no file behind.
    $this->actingAs($owner)->post("/briefs/{$brief->id}/audio");
    expect($brief->fresh()->audio_status)->toBe(AudioStatus::Failed);
    expect(Storage::disk('local')->allFiles('briefs/audio'))->toBe([]);

    // A ready brief whose file has vanished is shown as failed, with a retry.
    $brief->forceFill(['audio_status' => 'ready', 'audio_path' => 'briefs/audio/gone.mp3'])->save();
    $this->actingAs($owner)->get("/briefs/{$brief->id}")->assertInertia(fn ($page) => $page->where('audio.status', 'failed')->where('audio.url', null)->where('audio.can_request', true));
    expect($brief->fresh()->audio_status)->toBe(AudioStatus::Failed);
});
