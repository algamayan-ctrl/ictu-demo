<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The ICTU dashboard account cards and the User Accounts list they open must
 * count the same accounts. Retired legacy classifications are hidden from
 * the list, so they must not be counted on the cards either.
 */
class IctuAccountStatusKpiTest extends TestCase
{
    use RefreshDatabase;

    private User $ictu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->ictu = User::factory()->create(['access_classification' => AccessClassification::IctuMaintainer]);
        app(\App\Services\UserRoleAssignmentService::class)->synchronize($this->ictu, AccessClassification::IctuMaintainer);
    }

    private function account(AccessClassification $classification, string $status, string $name, bool $verified = true): User
    {
        return User::factory()->create([
            'access_classification' => $classification,
            'account_status' => $status,
            'full_name' => $name,
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function dashboard(): TestResponse
    {
        return $this->withSession(['active_workspace' => 'ICTU'])->actingAs($this->ictu)->get(route('dashboard'))->assertOk();
    }

    private function accounts(array $query = []): TestResponse
    {
        return $this->withSession(['active_workspace' => 'ICTU'])->actingAs($this->ictu)
            ->get(route('administration.users.index', $query))->assertOk();
    }

    public function test_a_retired_legacy_account_is_not_counted_and_the_empty_list_says_so(): void
    {
        $this->account(AccessClassification::BorrowerOnly, 'ACTIVE', 'Working Borrower');
        $this->account(AccessClassification::RetiredInactive, 'INACTIVE', 'Retired Legacy Person');
        $this->account(AccessClassification::GsuHead, 'INACTIVE', 'Legacy Signatory Person');

        $statistics = $this->dashboard()->viewData('statistics');
        $this->assertSame(0, $statistics['Inactive Accounts']);

        $list = $this->accounts(['status' => 'inactive']);
        $this->assertCount(0, $list->viewData('users'));
        $list->assertSee('No inactive accounts found.')
            ->assertDontSee('No institutional accounts have been registered.')
            ->assertDontSee('Retired Legacy Person')
            ->assertDontSee('Legacy Signatory Person');
    }

    public function test_one_inactive_account_is_counted_and_listed(): void
    {
        $this->account(AccessClassification::BorrowerOnly, 'INACTIVE', 'Deactivated Borrower');
        $this->account(AccessClassification::RetiredInactive, 'INACTIVE', 'Retired Legacy Person');

        $this->assertSame(1, $this->dashboard()->viewData('statistics')['Inactive Accounts']);

        $list = $this->accounts(['status' => 'inactive']);
        $this->assertCount(1, $list->viewData('users'));
        $list->assertSee('Deactivated Borrower')
            ->assertDontSee('Retired Legacy Person')
            ->assertDontSee('No inactive accounts found.');
    }

    public function test_several_inactive_and_suspended_accounts_match_the_card(): void
    {
        $this->account(AccessClassification::BorrowerOnly, 'INACTIVE', 'Deactivated Borrower');
        $this->account(AccessClassification::SpmuOfficer, 'INACTIVE', 'Deactivated Officer');
        $this->account(AccessClassification::BorrowerOnly, 'SUSPENDED', 'Suspended Borrower');
        $this->account(AccessClassification::BorrowerOnly, 'ACTIVE', 'Working Borrower');
        $this->account(AccessClassification::RetiredInactive, 'INACTIVE', 'Retired Legacy Person');

        $statistics = $this->dashboard()->viewData('statistics');
        $this->assertSame(3, $statistics['Inactive Accounts']);

        $inactive = $this->accounts(['status' => 'inactive']);
        $this->assertCount(3, $inactive->viewData('users'));
        $inactive->assertSee('Deactivated Officer')->assertSee('Suspended Borrower')->assertDontSee('Working Borrower');

        // Active card and list agree too (the ICTU account plus one borrower).
        $this->assertSame(2, $statistics['Active Accounts']);
        $this->assertCount(2, $this->accounts(['status' => 'active'])->viewData('users'));
    }

    public function test_active_inactive_and_pending_verification_stay_distinct(): void
    {
        $this->account(AccessClassification::BorrowerOnly, 'ACTIVE', 'Unverified Borrower', verified: false);
        $this->account(AccessClassification::BorrowerOnly, 'INACTIVE', 'Deactivated Borrower');
        $this->account(AccessClassification::RetiredInactive, 'ACTIVE', 'Retired Legacy Person', verified: false);

        $statistics = $this->dashboard()->viewData('statistics');
        $this->assertSame(2, $statistics['Active Accounts']);
        $this->assertSame(1, $statistics['Inactive Accounts']);
        $this->assertSame(1, $statistics['Pending Verification']);

        $pending = $this->accounts(['verification' => 'pending']);
        $this->assertCount(1, $pending->viewData('users'));
        $pending->assertSee('Unverified Borrower')->assertDontSee('Deactivated Borrower');

        $this->accounts(['status' => 'active'])->assertSee('Unverified Borrower')->assertDontSee('Deactivated Borrower');
    }

    public function test_empty_filters_name_the_filter_and_the_unfiltered_message_is_kept(): void
    {
        User::query()->whereKeyNot($this->ictu->id)->delete();
        $this->ictu->forceFill(['email_verified_at' => now()])->save();

        $this->accounts(['verification' => 'pending'])->assertSee('No accounts pending verification found.');
        $this->accounts(['status' => 'inactive'])->assertSee('No inactive accounts found.');
        $this->accounts()->assertDontSee('No inactive accounts found.');
    }

    public function test_the_inactive_card_opens_the_inactive_list(): void
    {
        $this->dashboard()->assertSee(route('administration.users.index', ['status' => 'inactive']), false);
    }

    public function test_only_ictu_can_open_the_inactive_accounts_list(): void
    {
        foreach ([AccessClassification::BorrowerOnly, AccessClassification::SpmuHead, AccessClassification::SpmuOfficer] as $classification) {
            $response = $this->actingAs(User::factory()->create(['access_classification' => $classification]))
                ->get(route('administration.users.index', ['status' => 'inactive']));

            $this->assertContains($response->getStatusCode(), [302, 403, 404], $classification->value);
        }
    }
}
