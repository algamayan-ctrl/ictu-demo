<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

/**
 * The suite rebuilds its database from nothing, so it must refuse any
 * database that is not provably disposable. APP_ENV=testing alone is not
 * proof: an environment can be pointed at a live or staging server by mistake.
 *
 * No database is touched here; only the decision is exercised.
 */
class DisposableTestDatabaseGuardTest extends TestCase
{
    private string|false $originalOptIn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalOptIn = getenv('SPMU_TEST_DATABASE');
    }

    protected function tearDown(): void
    {
        $this->originalOptIn === false
            ? putenv('SPMU_TEST_DATABASE')
            : putenv('SPMU_TEST_DATABASE='.$this->originalOptIn);

        parent::tearDown();
    }

    public function test_in_memory_sqlite_is_accepted(): void
    {
        $this->pointAt('sqlite', ':memory:');
        putenv('SPMU_TEST_DATABASE');

        $this->assertDisposableTestDatabase();

        $this->addToAssertionCount(1);
    }

    public function test_a_mysql_database_is_refused_without_an_explicit_choice(): void
    {
        $this->pointAt('mysql', 'spmu_test');
        putenv('SPMU_TEST_DATABASE');

        $this->assertRefused();
    }

    public function test_a_live_or_staging_database_is_refused_even_in_the_testing_environment(): void
    {
        foreach (['spmu_acpmp', 'laravel', 'main', 'staging'] as $database) {
            $this->pointAt('mysql', $database);

            /* Not chosen at all. */
            putenv('SPMU_TEST_DATABASE');
            $this->assertRefused();

            /* Chosen by name, but the name is not a test database's name. */
            putenv('SPMU_TEST_DATABASE='.$database);
            $this->assertRefused();
        }
    }

    public function test_the_choice_must_name_the_configured_database_exactly(): void
    {
        $this->pointAt('mysql', 'spmu_test');
        putenv('SPMU_TEST_DATABASE=another_test');

        $this->assertRefused();
    }

    public function test_a_named_disposable_test_database_is_accepted(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            $this->pointAt($connection, 'spmu_testsuite');
            putenv('SPMU_TEST_DATABASE=spmu_testsuite');

            $this->assertDisposableTestDatabase();
        }

        $this->addToAssertionCount(1);
    }

    public function test_an_sqlite_file_is_treated_like_any_other_real_database(): void
    {
        $this->pointAt('sqlite', '/var/www/html/database/database.sqlite');
        putenv('SPMU_TEST_DATABASE');

        $this->assertRefused();
    }

    public function test_nothing_runs_outside_the_testing_environment(): void
    {
        $this->pointAt('sqlite', ':memory:');

        foreach (['production', 'staging', 'local'] as $environment) {
            $this->app['env'] = $environment;

            $this->assertRefused();
        }

        $this->app['env'] = 'testing';
    }

    private function pointAt(string $connection, string $database): void
    {
        config([
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);
    }

    private function assertRefused(): void
    {
        try {
            $this->assertDisposableTestDatabase();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Tests refuse to run', $exception->getMessage());

            return;
        }

        $this->fail('The database ['.config('database.default').'] "'.config('database.connections.'.config('database.default').'.database').'" should have been refused.');
    }
}
