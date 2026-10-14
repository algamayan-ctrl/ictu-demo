<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The User Accounts status and verification filters belong to the SPMU
 * Admin/Head, who administers accounts now that ICTU Maintainer is retired.
 */
class AccountStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        app(\App\Services\UserRoleAssignmentService::class)->synchronize($this->head, AccessClassification::SpmuHead);
    }

    private function accounts(array $query = []): TestResponse
    {
        return $this->withSession(['active_workspace' => 'SPMU'])->actingAs($this->head)
            ->get(route('administration.users.index', $query))->assertOk();
    }

    public function test_empty_filters_name_the_filter_and_the_unfiltered_message_is_kept(): void
    {
        User::query()->whereKeyNot($this->head->id)->delete();
        $this->head->forceFill(['email_verified_at' => now()])->save();

        $this->accounts(['verification' => 'pending'])->assertSee('No accounts pending verification found.');
        $this->accounts(['status' => 'inactive'])->assertSee('No inactive accounts found.');
        $this->accounts()->assertDontSee('No inactive accounts found.');
    }

    public function test_only_the_spmu_head_can_open_the_inactive_accounts_list(): void
    {
        $this->accounts(['status' => 'inactive']);

        foreach ([AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer, AccessClassification::IctuMaintainer] as $classification) {
            $user = User::factory()->create(['access_classification' => $classification]);
            app(\App\Services\UserRoleAssignmentService::class)->synchronize($user, $classification);

            $response = $this->actingAs($user)->get(route('administration.users.index', ['status' => 'inactive']));

            $this->assertContains($response->getStatusCode(), [302, 403, 404], $classification->value);
        }
    }
}
