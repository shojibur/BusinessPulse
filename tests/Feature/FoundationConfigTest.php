<?php

use Tests\TestCase;

uses(TestCase::class);

test('the database queue is configured with retry_after 300 and after-commit dispatch', function () {
    expect(config('queue.connections.database.driver'))->toBe('database')
        ->and(config('queue.connections.database.retry_after'))->toBe(300)
        ->and(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and(config('cache.stores.database.driver'))->toBe('database');
});

test('the default filesystem disk is private with no public url', function () {
    expect(config('filesystems.default'))->toBe('local')
        ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.local.visibility'))->toBe('private')
        ->and(config('filesystems.disks.local.url'))->toBeNull()
        ->and(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and(config('filesystems.links'))->toBe([]);
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

test('PostgreSQL is the default database connection', function () {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.driver'))->toBe('pgsql');
});

test('the tests run against the businesspulse_testing database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('businesspulse_testing');
});

test('the test harness refuses to run against any other database', function () {
    config(['database.connections.pgsql.database' => 'businesspulse']);

    $case = new class('guard') extends TestCase
    {
        public function check(): void
        {
            $this->setUpTraits();
        }
    };
    $case->check();
})->throws(RuntimeException::class, 'Refusing to run tests');
