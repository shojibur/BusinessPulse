<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// No RefreshDatabase here: these tests exercise the guard itself, so nothing may be migrated or wiped.
uses(TestCase::class);

test('the test harness refuses to run when the configuration names another database', function () {
    config(['database.connections.pgsql.database' => 'businesspulse']);

    (new class('guard') extends TestCase
    {
        public function check(): void
        {
            $this->setUpTraits();
        }
    })->check();
})->throws(RuntimeException::class, 'Refusing to run tests');

test('the test harness asks PostgreSQL which database is really connected', function () {
    // Connect to the development database (read-only `select current_database()`), then make the
    // configuration claim the test database again: only the live check can catch the difference.
    config(['database.connections.pgsql.database' => 'businesspulse']);
    DB::purge('pgsql');
    expect(DB::selectOne('select current_database() as name')->name)->toBe('businesspulse');
    config(['database.connections.pgsql.database' => TestCase::TEST_DATABASE]);

    try {
        (new class('guard') extends TestCase
        {
            public function check(): void
            {
                $this->setUpTraits();
            }
        })->check();
    } finally {
        DB::purge('pgsql');
    }
})->throws(RuntimeException::class, 'PostgreSQL reports the connected database');
