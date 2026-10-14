<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdleSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::create(2026, 10, 12, 9));
        config(['session.idle_timeout' => 15]);
    }

    public function test_first_authenticated_request_initializes_activity_clock(): void
    {
        $borrower = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();

        $this->actingAs($borrower)->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('last_user_activity_at', now()->timestamp);
    }

    public function test_request_before_timeout_does_not_refresh_activity_clock(): void
    {
        $borrower = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();
        $lastActivity = now()->subMinutes(14)->timestamp;

        $this->actingAs($borrower)
            ->withSession(['last_user_activity_at' => $lastActivity])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('last_user_activity_at', $lastActivity);

        $this->assertAuthenticatedAs($borrower);
    }

    public function test_timeout_boundary_blocks_draft_creation_and_records_audit(): void
    {
        $borrower = User::query()->where('email', 'borrower@spmu.test')->firstOrFail();

        $this->actingAs($borrower)
            ->withSession(['last_user_activity_at' => now()->subMinutes(15)->timestamp])
            ->post(route('requests.store'), [])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('borrowing_requests', 0);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $borrower->id,
            'action_code' => 'SESSION_IDLE_TIMEOUT',
        ]);
    }
}
