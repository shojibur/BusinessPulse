<?php

use App\Models\AiSetting;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Services\AI\AiSettings;
use App\Services\AI\AnthropicTextClient;
use App\Services\AI\ChatRequest;
use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\AI\OpenAiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const AI_ADMIN_PASSWORD = 'platform-pass-123';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['ai.base_url' => null, 'ai.api_key' => null, 'ai.text_model' => null, 'ai.transcription_model' => null, 'ai.speech_model' => null, 'ai.speech_voice' => null, 'ai.text_provider' => 'openai', 'ai.anthropic_api_key' => null]);
    PlatformAdmin::create(['name' => 'Pat Platform', 'email' => 'pat@platform.test', 'password' => AI_ADMIN_PASSWORD]);
});

function aiSignedIn()
{
    test()->post('/platform/login', ['email' => 'pat@platform.test', 'password' => AI_ADMIN_PASSWORD]);

    return test();
}

function aiPayload(array $overrides = []): array
{
    return array_replace([
        'text_provider' => 'anthropic', 'text_base_url' => '', 'text_api_key' => 'sk-ant-secret-text-key', 'text_model' => 'claude-sonnet-5-5',
        'transcription_base_url' => 'https://api.openai.example.com/v1', 'transcription_api_key' => 'sk-secret-transcribe', 'transcription_model' => 'whisper-x',
        'speech_base_url' => 'https://api.openai.example.com/v1', 'speech_api_key' => 'sk-secret-speech', 'speech_model' => 'tts-x', 'speech_voice' => 'alloy',
    ], $overrides);
}

function chatRequest(): ChatRequest
{
    return new ChatRequest(
        messages: [['role' => 'system', 'content' => 'Be brief.'], ['role' => 'user', 'content' => 'Return the object.']],
        schema: ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean', 'minimum' => 0]]],
        briefId: 7,
    );
}

test('without platform settings the .env values apply, exactly as before', function () {
    config(['ai.base_url' => 'https://env.example.com/v1', 'ai.api_key' => 'env-key', 'ai.text_model' => 'env-text', 'ai.transcription_model' => 'env-tr', 'ai.speech_model' => 'env-sp', 'ai.speech_voice' => 'env-voice']);

    $settings = app(AiSettings::class);

    expect($settings->text()->provider)->toBe('openai')->and($settings->text()->model)->toBe('env-text')->and($settings->text()->source)->toBe('env')->and($settings->text()->configured())->toBeTrue()
        ->and($settings->transcription()->model)->toBe('env-tr')->and($settings->speech()->voice)->toBe('env-voice')->and($settings->speech()->configured())->toBeTrue()
        ->and(OpenAiClient::textConfigured())->toBeTrue();
});

test('platform settings win over .env per field, and an OpenAI key is never used for Claude', function () {
    config(['ai.base_url' => 'https://env.example.com/v1', 'ai.api_key' => 'env-openai-key', 'ai.text_model' => 'env-text']);
    AiSetting::create(['text_provider' => 'anthropic', 'text_model' => 'claude-sonnet-5-5']);

    $text = app(AiSettings::class)->text();

    expect($text->provider)->toBe('anthropic')->and($text->baseUrl)->toBe('https://api.anthropic.com')->and($text->model)->toBe('claude-sonnet-5-5')
        ->and($text->apiKey)->toBeNull()->and($text->configured())->toBeFalse()->and($text->source)->toBe('none');

    config(['ai.anthropic_api_key' => 'env-anthropic-key']);
    expect(app(AiSettings::class)->text()->apiKey)->toBe('env-anthropic-key')->and(app(AiSettings::class)->text()->source)->toBe('env');

    AiSetting::first()->update(['text_api_key' => 'saved-key']);
    expect(app(AiSettings::class)->text()->apiKey)->toBe('saved-key')->and(app(AiSettings::class)->text()->source)->toBe('platform');
    // Transcription still uses the OpenAI-compatible .env values.
    expect(app(AiSettings::class)->transcription()->apiKey)->toBe('env-openai-key');
});

test('API keys are encrypted at rest', function () {
    aiSignedIn()->put('/platform/ai', aiPayload())->assertRedirect('/platform/ai');

    $raw = DB::table('ai_settings')->first();

    expect($raw->text_api_key)->not->toContain('sk-ant-secret')->and($raw->transcription_api_key)->not->toContain('sk-secret')->and($raw->speech_api_key)->not->toContain('sk-secret')
        ->and(AiSetting::first()->text_api_key)->toBe('sk-ant-secret-text-key')
        ->and(AiSetting::first()->toArray())->not->toHaveKeys(['text_api_key', 'transcription_api_key', 'speech_api_key']);
});

test('the page never sends a key to the browser and shows what is saved and in use', function () {
    aiSignedIn()->put('/platform/ai', aiPayload());

    $page = test()->get('/platform/ai')->assertOk();
    $page->assertInertia(fn ($p) => $p->component('platform/AiSettings')
        ->where('values.text_provider', 'anthropic')->where('values.text_model', 'claude-sonnet-5-5')
        ->where('keys.text', true)->where('keys.transcription', true)
        ->where('status.text.configured', true)->where('status.text.source', 'platform')->where('status.text.provider', 'anthropic'));

    expect($page->getContent())->not->toContain('sk-ant-secret')->not->toContain('sk-secret-transcribe')->not->toContain('sk-secret-speech');
});

test('a blank key keeps the saved key, remove clears it, and the audit trail has field names only', function () {
    aiSignedIn()->put('/platform/ai', aiPayload());
    test()->put('/platform/ai', aiPayload(['text_api_key' => '', 'text_model' => 'claude-haiku-5-5']))->assertRedirect();

    expect(AiSetting::first()->text_api_key)->toBe('sk-ant-secret-text-key')->and(AiSetting::first()->text_model)->toBe('claude-haiku-5-5');

    test()->put('/platform/ai', aiPayload(['text_api_key' => '', 'clear_text_api_key' => true]))->assertRedirect();
    expect(AiSetting::first()->text_api_key)->toBeNull();

    $logs = PlatformAuditLog::where('action', 'ai.settings_updated')->get();
    expect($logs)->toHaveCount(3)->and(json_encode($logs->pluck('properties')))->not->toContain('sk-')->and(json_encode($logs->first()->properties['fields']))->toContain('text_api_key');
    expect(AiSetting::count())->toBe(1);
});

test('only a signed-in platform admin can open or change AI settings', function () {
    $f = meetingRoster();

    test()->get('/platform/ai')->assertRedirect('/platform/login');
    test()->put('/platform/ai', aiPayload())->assertRedirect('/platform/login');
    test()->post('/platform/ai/test')->assertRedirect('/platform/login');
    test()->actingAs($f->owner)->get('/platform/ai')->assertRedirect('/platform/login');
    test()->actingAs($f->owner)->put('/platform/ai', aiPayload())->assertRedirect('/platform/login');

    expect(AiSetting::count())->toBe(0);
});

test('invalid settings are rejected', function (array $override, string $field) {
    aiSignedIn()->put('/platform/ai', aiPayload($override))->assertSessionHasErrors($field);

    expect(AiSetting::count())->toBe(0);
})->with([
    'unknown provider' => [['text_provider' => 'gemini'], 'text_provider'],
    'http address' => [['transcription_base_url' => 'http://api.example.com/v1'], 'transcription_base_url'],
    'private ip' => [['speech_base_url' => 'https://10.0.0.5/v1'], 'speech_base_url'],
    'credentials in url' => [['text_base_url' => 'https://user:pw@api.example.com/v1'], 'text_base_url'],
]);

test('Claude requests use the Messages API with the right headers and a structured-output schema', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"ok": true}']], 'stop_reason' => 'end_turn'])]);
    aiSignedIn()->put('/platform/ai', aiPayload());

    $response = app(OpenAiClient::class)->chat(chatRequest());

    expect($response->data)->toBe(['ok' => true]);
    Http::assertSent(function (Request $r) {
        $body = $r->data();

        return $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->header('x-api-key') === ['sk-ant-secret-text-key'] && $r->header('anthropic-version') === [AnthropicTextClient::API_VERSION]
            && $body['model'] === 'claude-sonnet-5-5' && $body['system'] === 'Be brief.' && $body['messages'] === [['role' => 'user', 'content' => 'Return the object.']]
            && $body['output_config']['format']['type'] === 'json_schema' && ! array_key_exists('minimum', $body['output_config']['format']['schema']['properties']['ok'])
            && ! array_key_exists('temperature', $body) && ! array_key_exists('top_p', $body) && ! isset($r->header('authorization')[0]);
    });
});

test('Claude failures map to the same recoverable and final errors, and refusals are never used', function () {
    aiSignedIn()->put('/platform/ai', aiPayload());
    // One fake with a sequence: the first matching stub wins, so each call gets the next answer.
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push([], 429)->push([], 500)->push([], 401)
        ->push(['content' => [], 'stop_reason' => 'refusal'])
        ->push(['content' => [['type' => 'text', 'text' => 'not json']], 'stop_reason' => 'end_turn'])]);
    $chat = fn () => app(OpenAiClient::class)->chat(chatRequest());

    expect($chat)->toThrow(fn (RecoverableProviderException $e) => $e->errorCode() === 'rate_limited');
    expect($chat)->toThrow(fn (RecoverableProviderException $e) => $e->errorCode() === 'server_error');
    expect($chat)->toThrow(fn (ProviderException $e) => $e->errorCode() === 'client_error' && ! $e instanceof RecoverableProviderException);
    expect($chat)->toThrow(fn (ProviderException $e) => $e->errorCode() === 'invalid_response');
    expect($chat)->toThrow(fn (ProviderException $e) => $e->errorCode() === 'invalid_response');
});

test('the OpenAI-compatible path uses the saved address, key and model', function () {
    Http::fake(['openai.example.com/*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]])]);
    aiSignedIn()->put('/platform/ai', aiPayload(['text_provider' => 'openai', 'text_base_url' => 'https://openai.example.com/v1', 'text_api_key' => 'sk-saved-openai', 'text_model' => 'text-x']));

    app(OpenAiClient::class)->chat(chatRequest());

    Http::assertSent(fn (Request $r) => $r->url() === 'https://openai.example.com/v1/chat/completions' && $r->hasHeader('Authorization', 'Bearer sk-saved-openai') && $r->data()['model'] === 'text-x');
});

test('the connection test reports plain results and never shows provider text or keys', function () {
    aiSignedIn()->put('/platform/ai', aiPayload());
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => '{"ok": true}']], 'stop_reason' => 'end_turn'])
        ->push(['error' => ['message' => 'secret provider detail sk-ant-secret-text-key']], 401)]);

    test()->post('/platform/ai/test')->assertRedirect('/platform/ai');
    expect(session('flash.type'))->toBe('success');

    test()->post('/platform/ai/test')->assertRedirect('/platform/ai');
    expect(session('flash.type'))->toBe('error')->and(session('flash.message'))->not->toContain('sk-ant')->not->toContain('secret provider detail');

    expect(PlatformAuditLog::where('action', 'ai.connection_tested')->count())->toBe(2);
});
