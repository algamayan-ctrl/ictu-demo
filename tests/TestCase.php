<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
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
