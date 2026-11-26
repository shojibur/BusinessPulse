<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

test('the database queue is configured with retry_after 300 and after-commit dispatch', function () {
    expect(config('queue.connections.database.driver'))->toBe('database')
        ->and(config('queue.connections.database.retry_after'))->toBe(300)
        ->and(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and(config('cache.stores.database.driver'))->toBe('database');
});

test('the AI configuration has timeouts, no retry setting and no hardcoded model names', function () {
    $ai = config('ai');

    expect($ai['timeouts'])->toBe(['text' => 30, 'speech' => 60, 'transcription' => 120, 'extraction' => 60])
        ->and(array_keys($ai))->not->toContain('retry', 'retries', 'backoff')
        ->and($ai['text_model'])->toBeNull()
        ->and($ai['transcription_model'])->toBeNull()
        ->and($ai['speech_model'])->toBeNull()
        ->and($ai['api_key'])->toBeNull();
});

test('the tests run against the businesspulse_testing database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('businesspulse_testing');
});

test('behind a trusted proxy that terminates https, addresses are built with https', function () {
    TrustProxies::flushState();
    config(['app.trusted_proxies' => '*']);
    app()->getProvider(AppServiceProvider::class)->configureTrustedProxies();

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'app.example.com'])->get('/login')->assertOk();

    expect(url('/'))->toBe('https://app.example.com')->and(asset('build/app.js'))->toStartWith('https://app.example.com/');
    TrustProxies::flushState();
});

test('without trusted proxies the forwarded headers are ignored', function () {
    TrustProxies::flushState();

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])->get('/login')->assertOk();

    expect(url('/'))->not->toContain('evil.example.com')->and(url('/'))->not->toStartWith('https://');
});
