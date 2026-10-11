<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The idle-session timeout must stay enforced in the test environment.
 * Tests\TestCase starts a fresh idle clock on each sign-in; these tests
 * prove a session left idle without a new sign-in is still ended.
 */
class IdleSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): User
    {
        return User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
    }

    public function test_a_session_idle_past_the_limit_is_ended_and_sent_to_sign_in(): void
    {
        $minutes = (int) config('session.idle_timeout');
        $this->assertSame(15, $minutes);

        $this->actingAs($this->borrower())->get(route('dashboard'))->assertOk();

        $this->travel($minutes + 1)->minutes();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', "Your session ended after {$minutes} minutes of inactivity. Please sign in again.");

        $this->assertGuest();
        $this->assertTrue(AuditEvent::query()->where('action_code', 'SESSION_IDLE_TIMEOUT')->exists());
    }

    public function test_a_session_inside_the_limit_stays_signed_in(): void
    {
        $user = $this->borrower();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->travel((int) config('session.idle_timeout') - 1)->minutes();

        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_ordinary_requests_do_not_extend_the_idle_clock(): void
    {
        $this->actingAs($this->borrower())->get(route('dashboard'))->assertOk();

        $this->travel(10)->minutes();
        $this->get(route('dashboard'))->assertOk();

        $this->travel(10)->minutes();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_post_after_the_limit_is_not_carried_out(): void
    {
        $user = $this->borrower();
        $before = $user->designation;

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->travel(1)->days();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame($before, $user->fresh()->designation);
    }
}
