<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to touch any database that is not provably disposable.
     *
     * This runs before the testing traits do. RefreshDatabase wipes and
     * rebuilds the configured database as its first act, so a check made
     * later, inside a test's own setUp(), comes after the damage.
     *
     * Allowed without further ceremony: the in-memory SQLite database the
     * suite is configured for. Any other database (a MySQL or MariaDB server,
     * or an SQLite file) is allowed only when all of these hold:
     *
     *  - the environment is "testing";
     *  - SPMU_TEST_DATABASE names that exact database, so someone chose it
     *    on purpose for this run;
     *  - its name contains "test", so it cannot be a database that was named
     *    for real use.
     *
     * APP_ENV=testing alone is never enough: an environment pointed at a
     * live or staging database by mistake still fails all three.
     */
    protected function setUpTraits()
    {
        $this->assertDisposableTestDatabase();

        return parent::setUpTraits();
    }

    protected function assertDisposableTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if (! $this->app->environment('testing')) {
            throw new RuntimeException('Tests refuse to run outside the "testing" environment (current: '.$this->app->environment().').');
        }

        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }

        $optIn = (string) (getenv('SPMU_TEST_DATABASE') ?: '');

        if ($optIn === '' || $optIn !== $database || stripos($database, 'test') === false) {
            throw new RuntimeException(
                "Tests refuse to run against the [{$connection}] database \"{$database}\": the suite rebuilds its database from nothing. "
                .'Use the in-memory SQLite database, or set SPMU_TEST_DATABASE to the exact name of a disposable database whose name contains "test".'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The test environment's array cache driver is process-lifetime,
         * not per-test. RefreshDatabase rolls back auto-increment state
         * between tests, so two unrelated tests can legitimately produce
         * the exact same user/route/record-id/body combination.
         * PreventDuplicateSubmission's cache-based dedup would otherwise
         * treat the second test's first, genuine submission as a leftover
         * "already completed" duplicate from an earlier, unrelated test.
         * Flushing here isolates tests from each other without touching
         * the real (unflushed, request-lifetime) production behavior.
         */
        Cache::flush();
    }

    /**
     * Signing a user in starts a new idle-session clock, exactly as a real
     * login does (the session is regenerated, and EnforceIdleSession starts
     * counting from the first request).
     *
     * Workflow tests move the clock forward by hours or days between steps
     * (travelTo / setTestNow) to reach a pickup window or a due date, then
     * call actingAs() for the next step. Without this, that next request was
     * answered by the idle-session logout - a redirect to /login with no
     * validation errors - so assertSessionHasNoErrors() passed while the
     * step under test never ran.
     *
     * This does not disable the middleware: a test that signs in once and
     * then lets the clock run still gets logged out.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app->bound('session')) {
            $this->app['session']->forget('last_user_activity_at');
        }

        return parent::actingAs($user, $guard);
    }
}
