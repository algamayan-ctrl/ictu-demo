<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
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
     * Signing in as a user starts that user's idle clock at the current
     * (possibly time-travelled) moment, exactly as a real sign-in does.
     *
     * Workflow tests move the clock forward by hours or days between steps
     * and sign in again for each step. Without this, the single test session
     * would carry the first step's activity time forward and
     * EnforceIdleSession would correctly end it before the later step ran.
     * The middleware and its timeout stay fully active: a session that is
     * left idle past the limit without a new sign-in is still ended (see
     * IdleSessionTimeoutTest).
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession(['last_user_activity_at' => now()->timestamp]);
    }
}
