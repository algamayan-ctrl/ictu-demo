<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Http\Controllers\UserAdministrationController;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailVerificationService;
use App\Services\NotificationService;
use App\Services\UserRoleAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    public static function operationalRoles(): array
    {
        return [
            'Borrower' => ['BORROWER_ONLY', 'BORROWER'],
            'SAO' => ['SPMU_OFFICER', 'SPMU'],
            'Admin' => ['SPMU_HEAD', 'SPMU'],
        ];
    }

    #[DataProvider('operationalRoles')]
    public function test_admin_registers_operational_accounts_with_otp_and_audit(string $classification, string $role): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);

        $this->actingAs($admin)->post(route('administration.users.store'), $this->payload($classification))
            ->assertRedirect(route('administration.users.index'))
            ->assertSessionHasNoErrors();

        $account = User::query()->where('email', 'admin.registered@cspc.edu.ph')->firstOrFail();
        $this->assertSame($classification, $account->access_classification->value);
        $this->assertNull($account->email_verified_at);
        $this->assertSame([$role], $account->roles()->wherePivotNull('revoked_at')->pluck('role_code')->all());
        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $account->id]);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $admin->id,
            'action_code' => 'USER_ACCOUNT_CREATED',
            'record_id' => $account->id,
        ]);
    }

    public function test_admin_directory_and_form_exclude_ictu_accounts_and_actions(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $ictu = $this->actor(AccessClassification::IctuMaintainer);

        $this->actingAs($admin)->get(route('administration.users.index'))
            ->assertOk()
            ->assertDontSee($ictu->email)
            ->assertSee(route('administration.users.deactivate', $this->actor(AccessClassification::BorrowerOnly)), false)
            ->assertSee('You cannot deactivate your own account');
        $this->get(route('administration.users.create'))->assertOk()
            ->assertDontSee('value="ICTU_MAINTAINER"', false);
    }

    public function test_admin_cannot_assign_ictu_classification(): void
    {
        $this->actingAs($this->actor(AccessClassification::SpmuHead))
            ->post(route('administration.users.store'), $this->payload('ICTU_MAINTAINER'))
            ->assertSessionHasErrors('access_classification');

        $this->assertDatabaseMissing('users', ['email' => 'admin.registered@cspc.edu.ph']);
    }

    public function test_admin_cannot_read_or_update_ictu_account(): void
    {
        $ictu = $this->actor(AccessClassification::IctuMaintainer);
        $before = $ictu->getAttributes();

        $this->actingAs($this->actor(AccessClassification::SpmuHead))
            ->get(route('administration.users.edit', $ictu))->assertForbidden();
        $this->put(route('administration.users.update', $ictu), $this->payload())
            ->assertForbidden();

        $this->assertSame($before, $ictu->fresh()->getAttributes());
    }

    public static function unauthorizedActors(): array
    {
        return ['Borrower' => ['BORROWER_ONLY'], 'SAO' => ['SPMU_OFFICER']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_other_operational_actors_cannot_manage_accounts(string $classification): void
    {
        $this->actingAs($this->actor(AccessClassification::from($classification)))
            ->get(route('administration.users.index'))->assertForbidden();
        $this->get(route('administration.users.create'))->assertForbidden();
        $this->post(route('administration.users.store'), $this->payload())->assertForbidden();
        $this->put(route('administration.users.update', $this->actor(AccessClassification::SpmuHead)), $this->payload())
            ->assertForbidden();
    }

    public function test_admin_role_change_revokes_obsolete_role_and_records_actor(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $borrower = $this->actor(AccessClassification::BorrowerOnly);
        $data = $this->payload('SPMU_OFFICER');
        $data['employee_no'] = $borrower->employee_no;
        $data['email'] = $borrower->email;
        unset($data['password'], $data['password_confirmation']);

        $this->actingAs($admin)->put(route('administration.users.update', $borrower), $data)
            ->assertRedirect(route('administration.users.index'))->assertSessionHasNoErrors();

        $this->assertSame('SPMU_OFFICER', $borrower->fresh()->access_classification->value);
        $this->assertSame(['SPMU'], $borrower->roles()->wherePivotNull('revoked_at')->pluck('role_code')->all());
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $admin->id,
            'action_code' => 'USER_ACCOUNT_UPDATED',
            'record_id' => $borrower->id,
        ]);
    }

    public function test_admin_cannot_grant_ictu_role_to_existing_operational_account(): void
    {
        $borrower = $this->actor(AccessClassification::BorrowerOnly);
        $before = $borrower->getAttributes();

        $this->actingAs($this->actor(AccessClassification::SpmuHead))
            ->put(route('administration.users.update', $borrower), $this->payload('ICTU_MAINTAINER'))
            ->assertSessionHasErrors('access_classification');

        $this->assertSame($before, $borrower->fresh()->getAttributes());
    }

    private function actor(AccessClassification $classification): User
    {
        return User::query()->where('access_classification', $classification->value)->firstOrFail();
    }

    public function test_invalid_registration_keeps_database_unchanged(): void
    {
        $data = $this->payload();
        $data['full_name'] = '';
        $data['password_confirmation'] = 'Does-not-match-2026!';

        $this->actingAs($this->actor(AccessClassification::SpmuHead))
            ->post(route('administration.users.store'), $data)
            ->assertSessionHasErrors(['full_name', 'password']);

        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_admin_cannot_demote_or_disable_own_account(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $data = $this->payload();
        $data['employee_no'] = $admin->employee_no;
        $data['email'] = $admin->email;
        $data['account_status'] = 'INACTIVE';
        $data['organizational_unit_id'] = $admin->organizational_unit_id;
        $data['division_code'] = $admin->organizationalUnit->divisionCode();
        unset($data['password'], $data['password_confirmation']);

        $this->actingAs($admin)->put(route('administration.users.update', $admin), $data)
            ->assertRedirect(route('administration.users.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'account_status' => 'ACTIVE',
            'access_classification' => 'SPMU_HEAD',
        ]);
    }

    public function test_admin_cannot_call_ictu_backup_or_pending_deletion_actions(): void
    {
        $this->actingAs($this->actor(AccessClassification::SpmuHead))
            ->post(route('administration.backup'))->assertForbidden();
        $this->delete(route('administration.users.delete-pending', $this->actor(AccessClassification::BorrowerOnly)))
            ->assertForbidden();
    }

    public function test_admin_cannot_update_a_target_that_became_ictu_after_binding(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $target = $this->actor(AccessClassification::BorrowerOnly);
        $data = $this->payload();
        $data['employee_no'] = $target->employee_no;
        $data['email'] = $target->email;
        unset($data['password'], $data['password_confirmation']);
        // The bound model remains operational; a separate update changes its scope.
        DB::table('users')->where('id', $target->id)->update(['access_classification' => 'ICTU_MAINTAINER']);
        $request = Request::create('/administration/users/'.$target->id, 'PUT', $data);
        $request->setUserResolver(fn (): User => $admin);
        $this->actingAs($admin);

        try {
            app(UserAdministrationController::class)->update(
                $request, $target, app(AuditService::class), app(UserRoleAssignmentService::class), app(NotificationService::class),
            );
            $this->fail('The updated target scope must be checked again before writing.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('users', ['id' => $target->id, 'access_classification' => 'ICTU_MAINTAINER']);
        $this->assertDatabaseMissing('audit_events', ['action_code' => 'USER_ACCOUNT_UPDATED', 'record_id' => $target->id]);
    }

    public static function disallowedIdentityStates(): array
    {
        return ['inactive' => ['INACTIVE', true], 'unverified' => ['ACTIVE', false]];
    }

    #[DataProvider('disallowedIdentityStates')]
    public function test_account_creation_requires_active_verified_actor_without_route_middleware(string $status, bool $verified): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $admin->forceFill(['account_status' => $status, 'email_verified_at' => $verified ? now() : null])->save();
        $request = Request::create('/administration/users', 'POST', $this->payload());
        $request->setUserResolver(fn (): User => $admin);

        try {
            app(UserAdministrationController::class)->store(
                $request, app(AuditService::class), app(UserRoleAssignmentService::class), app(EmailVerificationService::class),
            );
            $this->fail('Inactive or unverified actors must not register accounts.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('users', ['email' => 'admin.registered@cspc.edu.ph']);
    }

    private function payload(string $classification = 'BORROWER_ONLY'): array
    {
        $unit = OrganizationalUnit::query()->where('unit_code', $classification === 'BORROWER_ONLY' ? 'CCS' : 'SPMU')->firstOrFail();

        return [
            'division_code' => $unit->divisionCode(),
            'organizational_unit_id' => $unit->id,
            'employee_no' => 'ADMIN-REGISTERED-01',
            'full_name' => 'Admin Registered Account',
            'designation' => 'Administrative Aide',
            'employment_type' => 'STAFF',
            'employment_status' => 'FULL_TIME',
            'email' => 'admin.registered@cspc.edu.ph',
            'mobile_no' => '09171234567',
            'access_classification' => $classification,
            'account_status' => 'ACTIVE',
            'password' => 'Test-Only-Password-2026!',
            'password_confirmation' => 'Test-Only-Password-2026!',
        ];
    }
}
