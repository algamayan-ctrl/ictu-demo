<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserRoleAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IctuPendingRegistrationDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seed_demo_users' => true, 'services.institutional_email.allowed_domains' => 'cspc.edu.ph,spmu.test']);
        $this->seed(DatabaseSeeder::class);
    }

    private function ictu(): User
    {
        $actor = User::factory()->create([
            'email' => 'ictu.pending-cleanup@cspc.edu.ph',
            'access_classification' => AccessClassification::IctuMaintainer,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::where('unit_code', 'ICTU')->firstOrFail()->id,
        ]);
        app(UserRoleAssignmentService::class)->synchronize($actor, AccessClassification::IctuMaintainer);

        return $actor;
    }

    public function test_manage_form_shows_paired_additional_unit_fields_and_delete_only_for_pending_accounts(): void
    {
        $ictu = $this->ictu();
        $pending = User::factory()->unverified()->create(['email' => 'wrong-address@cspc.edu.ph']);
        $verified = User::factory()->create(['email' => 'verified-address@cspc.edu.ph']);

        $this->actingAs($ictu)->get(route('administration.users.edit', $pending))
            ->assertOk()
            ->assertSee('id="ictu-additional-division"', false)
            ->assertSee('id="ictu-additional-unit-picker"', false)
            ->assertSee('Delete Account')
            ->assertSee(route('administration.users.delete-pending', $pending), false)
            ->assertSee('ictu-form-action-right');

        $this->get(route('administration.users.edit', $verified))
            ->assertOk()
            ->assertDontSee('id="ictu-delete-pending-registration-button"', false)
            ->assertDontSee(route('administration.users.delete-pending', $verified), false);

        $this->get(route('administration.users.create'))
            ->assertOk()->assertSee('Create Account')->assertDontSee('Delete Account');
    }

    public function test_manage_form_keeps_verified_lifecycle_actions_in_the_user_accounts_list(): void
    {
        $ictu = $this->ictu();
        $active = User::factory()->create(['email' => 'active-lifecycle@cspc.edu.ph']);
        $inactive = User::factory()->create([
            'email' => 'inactive-lifecycle@cspc.edu.ph',
            'account_status' => AccountStatus::Inactive,
        ]);
        $linkedPending = User::factory()->unverified()->create(['email' => 'linked-pending@cspc.edu.ph']);
        DB::table('borrower_restrictions')->insert([
            'borrower_user_id' => $linkedPending->id,
            'restriction_type' => 'ADMIN_REVIEW',
            'reason' => 'Preservation test',
            'status' => 'ACTIVE',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ictu)->get(route('administration.users.edit', $active))
            ->assertOk()
            ->assertSee('Account deactivation is handled in the User Accounts list.')
            ->assertDontSee('Deactivate Account')
            ->assertDontSee('Reactivate Account')
            ->assertDontSee('Delete Account')
            ->assertDontSee(route('administration.users.deactivate', $active), false)
            ->assertDontSee(route('administration.users.reactivate', $active), false);

        $this->get(route('administration.users.edit', $inactive))
            ->assertOk()
            ->assertSee('Account deactivation is handled in the User Accounts list.')
            ->assertDontSee('Deactivate Account')
            ->assertDontSee('Reactivate Account')
            ->assertDontSee('Delete Account')
            ->assertDontSee(route('administration.users.deactivate', $inactive), false)
            ->assertDontSee(route('administration.users.reactivate', $inactive), false);

        $this->get(route('administration.users.edit', $linkedPending))
            ->assertOk()
            ->assertDontSee('Delete Account')
            ->assertSee('This account cannot be deleted because linked system records must be preserved.')
            ->assertDontSee(route('administration.users.delete-pending', $linkedPending), false);
    }

    public function test_manage_form_explains_why_an_unverified_account_cannot_be_deleted(): void
    {
        $ictu = $this->ictu();
        $previouslySignedIn = User::factory()->unverified()->create([
            'email' => 'previously-signed-in@cspc.edu.ph',
            'last_login_at' => now(),
        ]);
        $activeSession = User::factory()->unverified()->create(['email' => 'active-session@cspc.edu.ph']);
        DB::table('sessions')->insert([
            'id' => str_repeat('s', 40),
            'user_id' => $activeSession->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($ictu)->get(route('administration.users.edit', $previouslySignedIn))
            ->assertOk()
            ->assertDontSee('id="ictu-delete-pending-registration-button"', false)
            ->assertSee('This account cannot be deleted because it has previously signed in.');

        $this->get(route('administration.users.edit', $activeSession))
            ->assertOk()
            ->assertDontSee('id="ictu-delete-pending-registration-button"', false)
            ->assertSee('This account cannot be deleted while an active session exists.');
        $this->delete(route('administration.users.delete-pending', $activeSession))
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $activeSession->id]);

        $pendingIctu = User::factory()->create([
            'email' => 'self-protected-pending@cspc.edu.ph',
            'access_classification' => AccessClassification::IctuMaintainer,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::where('unit_code', 'ICTU')->firstOrFail()->id,
        ]);
        app(UserRoleAssignmentService::class)->synchronize($pendingIctu, AccessClassification::IctuMaintainer);
        $this->actingAs($pendingIctu)->get(route('administration.users.edit', $pendingIctu))
            ->assertOk()
            ->assertDontSee('id="ictu-delete-pending-registration-button"', false)
            ->assertSee('You cannot delete the account you are currently using.');
    }

    public function test_ictu_deletes_only_unused_pending_registration_and_keeps_an_audit_trail(): void
    {
        $ictu = $this->ictu();
        Mail::fake();
        $ccs = OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail();
        $this->actingAs($ictu)->post(route('administration.users.store'), [
            'division_code' => $ccs->divisionCode(),
            'organizational_unit_id' => $ccs->id,
            'employee_no' => 'ICTU-TYPO-001',
            'full_name' => 'Mistyped Registration',
            'designation' => 'Staff',
            'employment_type' => 'STAFF',
            'employment_status' => 'FULL_TIME',
            'email' => 'mistyped@cspc.edu.ph',
            'access_classification' => 'BORROWER_ONLY',
            'account_status' => 'ACTIVE',
            'password' => 'Complex!Pass2026',
            'password_confirmation' => 'Complex!Pass2026',
        ])->assertRedirect(route('administration.users.index'));

        $pending = User::where('email', 'mistyped@cspc.edu.ph')->firstOrFail();
        $this->assertNull($pending->email_verified_at);

        $this->delete(route('administration.users.delete-pending', $pending))
            ->assertRedirect(route('administration.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $pending->id]);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $pending->id]);
        $this->assertDatabaseMissing('user_organizational_units', ['user_id' => $pending->id]);
        $this->assertDatabaseHas('audit_events', [
            'action_code' => 'USER_PENDING_REGISTRATION_DELETED',
            'actor_user_id' => $ictu->id,
            'record_type' => User::class,
            'record_id' => $pending->id,
        ]);
    }

    public function test_deletion_rejects_verified_and_previously_signed_in_users(): void
    {
        $this->actingAs($this->ictu());
        $verified = User::factory()->create(['email' => 'verified-cleanup@cspc.edu.ph']);
        $signedIn = User::factory()->unverified()->create([
            'email' => 'signed-in-cleanup@cspc.edu.ph',
            'last_login_at' => now(),
        ]);
        $this->delete(route('administration.users.delete-pending', $verified))
            ->assertSessionHasErrors('account');
        $this->delete(route('administration.users.delete-pending', $signedIn))
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $verified->id]);
        $this->assertDatabaseHas('users', ['id' => $signedIn->id]);
    }

    public function test_deletion_rejects_pending_user_with_cascading_operational_restriction(): void
    {
        $this->actingAs($this->ictu());
        $pending = User::factory()->unverified()->create(['email' => 'linked-cleanup@cspc.edu.ph']);
        DB::table('borrower_restrictions')->insert([
            'borrower_user_id' => $pending->id,
            'restriction_type' => 'ADMIN_REVIEW',
            'reason' => 'Preservation test',
            'status' => 'ACTIVE',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete(route('administration.users.delete-pending', $pending))
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $pending->id]);
        $this->assertDatabaseHas('borrower_restrictions', ['borrower_user_id' => $pending->id]);
    }

    public function test_deletion_is_refused_with_an_explanation_for_any_restriction_status(): void
    {
        $this->actingAs($this->ictu());

        foreach (['ACTIVE', 'LIFTED'] as $index => $status) {
            $pending = User::factory()->unverified()->create(['email' => "restricted-{$index}@cspc.edu.ph"]);
            DB::table('borrower_restrictions')->insert([
                'borrower_user_id' => $pending->id,
                'restriction_type' => 'ADMIN_REVIEW',
                'reason' => 'Preservation test',
                'status' => $status,
                'effective_from' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->delete(route('administration.users.delete-pending', $pending))
                ->assertRedirect(route('administration.users.edit', $pending))
                ->assertSessionHasErrors([
                    'account' => 'This account cannot be deleted because linked system records must be preserved.',
                ]);

            $this->assertDatabaseHas('users', ['id' => $pending->id]);
            $this->assertDatabaseHas('borrower_restrictions', ['borrower_user_id' => $pending->id, 'status' => $status]);
            $this->assertDatabaseMissing('audit_events', [
                'action_code' => 'USER_PENDING_REGISTRATION_DELETED',
                'record_id' => $pending->id,
            ]);

            // The manage form offers no Delete button for this account.
            $this->get(route('administration.users.edit', $pending))
                ->assertOk()
                ->assertDontSee('id="ictu-delete-pending-registration-button"', false)
                ->assertSee('This account cannot be deleted because linked system records must be preserved.');
        }
    }

    public function test_deletion_is_refused_when_the_pending_account_has_a_borrowing_request(): void
    {
        $this->actingAs($this->ictu());
        $pending = User::factory()->unverified()->create(['email' => 'has-request@cspc.edu.ph']);
        DB::table('borrowing_requests')->insert([
            'request_no' => 'REQ-PENDING-DELETE-TEST',
            'borrower_user_id' => $pending->id,
            'accountable_unit_id' => OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail()->id,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Refused with the explanation, not with a database foreign-key error.
        $this->delete(route('administration.users.delete-pending', $pending))
            ->assertRedirect(route('administration.users.edit', $pending))
            ->assertSessionHasErrors('account');
        $this->assertDatabaseHas('users', ['id' => $pending->id]);
        $this->assertDatabaseHas('borrowing_requests', ['borrower_user_id' => $pending->id]);
    }

    public function test_an_unused_pending_registration_with_no_borrowing_records_is_still_deleted(): void
    {
        $ictu = $this->ictu();
        $this->actingAs($ictu);
        $pending = User::factory()->unverified()->create(['email' => 'unused-pending@cspc.edu.ph']);
        $other = User::factory()->unverified()->create(['email' => 'other-restricted@cspc.edu.ph']);

        // Another account's restriction must not block this one.
        DB::table('borrower_restrictions')->insert([
            'borrower_user_id' => $other->id,
            'restriction_type' => 'ADMIN_REVIEW',
            'reason' => 'Belongs to a different account',
            'status' => 'ACTIVE',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('administration.users.edit', $pending))
            ->assertOk()
            ->assertSee('id="ictu-delete-pending-registration-button"', false);

        $this->delete(route('administration.users.delete-pending', $pending))
            ->assertRedirect(route('administration.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $pending->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('borrower_restrictions', ['borrower_user_id' => $other->id]);
        $this->assertDatabaseHas('audit_events', [
            'action_code' => 'USER_PENDING_REGISTRATION_DELETED',
            'actor_user_id' => $ictu->id,
            'record_id' => $pending->id,
        ]);
    }

    public function test_non_ictu_cannot_delete_pending_registration(): void
    {
        $borrower = User::factory()->create(['email' => 'ordinary-borrower@cspc.edu.ph']);
        $pending = User::factory()->unverified()->create(['email' => 'other-pending@cspc.edu.ph']);
        $this->actingAs($borrower)->delete(route('administration.users.delete-pending', $pending))
            ->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $pending->id]);
    }
}
