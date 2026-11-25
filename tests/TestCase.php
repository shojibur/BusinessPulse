<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only database automated tests may touch. RefreshDatabase wipes it.
     */
    public const TEST_DATABASE = 'businesspulse_testing';

    /**
     * Refuse to boot test traits (which migrate and wipe the database) unless the
     * active connection is PostgreSQL database `businesspulse_testing`.
     */
    protected function setUpTraits()
    {
        $default = config('database.default');
        $database = config("database.connections.{$default}.database");

        if ($default !== 'pgsql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: they must use the pgsql database ['.self::TEST_DATABASE.'], '
                ."but the active connection is [{$default}] database [{$database}]. "
                .'Check phpunit.xml and .env.testing.'
            );
        }

        return parent::setUpTraits();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
