<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only database automated tests may touch. RefreshDatabase wipes it.
     */
    public const TEST_DATABASE = 'businesspulse_testing';

    protected function setUp(): void
    {
        parent::setUp();

        // Page responses must not depend on a built frontend (the Vite manifest).
        $this->withoutVite();
    }

    /**
     * Refuse to boot test traits (which migrate and wipe the database) unless the
     * active connection is PostgreSQL database `businesspulse_testing`.
     */
    protected function setUpTraits()
    {
        $default = config('database.default');
        $database = config("database.connections.{$default}.database");

        // Check the configuration first, then ask PostgreSQL which database is really connected, before
        // any test trait can migrate or wipe it.
        if ($default !== 'pgsql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: they must use the pgsql database ['.self::TEST_DATABASE.'], '
                ."but the active connection is [{$default}] database [{$database}]. "
                .'Check phpunit.xml and .env.testing.'
            );
        }

        $connected = DB::selectOne('select current_database() as name')->name;

        if ($connected !== self::TEST_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: PostgreSQL reports the connected database is ['.$connected.'], '
                .'not ['.self::TEST_DATABASE.'].'
            );
        }

        return parent::setUpTraits();
    }
}
