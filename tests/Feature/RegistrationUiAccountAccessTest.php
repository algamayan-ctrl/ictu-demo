<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Http\Controllers\UserAdministrationController;
use App\Models\AuditEvent;
use App\Models\BorrowerRegistration;
use App\Models\BorrowingRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationUiAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
    }

    private function actor(AccessClassification $classification): User
    {
        return User::where('access_classification', $classification)->firstOrFail();
    }

    public static function accountManagers(): array
    {
        return ['Admin' => ['SPMU_HEAD']];
    }

    #[DataProvider('accountManagers')]
    public function test_deactivation_preserves_history_and_creates_one_actor_audit(string $classification): void
    {
        $actor = $this->actor(AccessClassification::from($classification));
        $borrower = $this->actor(AccessClassification::BorrowerOnly);
        $roles = $borrower->roles()->pluck('roles.id')->all();
        $request = BorrowingRequest::create([
            'request_no' => 'UI-HISTORY-01', 'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id, 'current_version_no' => 1, 'status' => 'DRAFT',
        ]);
        $this->actingAs($actor)->patch(route('administration.users.deactivate', $borrower))
            ->assertRedirect(route('administration.users.index'))->assertSessionHasNoErrors();
        $this->patch(route('administration.users.deactivate', $borrower))->assertRedirect();
        $this->assertSame(AccountStatus::Inactive, $borrower->fresh()->account_status);
        $this->assertDatabaseHas('borrowing_requests', ['id' => $request->id, 'borrower_user_id' => $borrower->id]);
        $this->assertSame($roles, $borrower->roles()->pluck('roles.id')->all());
        $events = AuditEvent::where('record_id', $borrower->id)->where('record_type', User::class)->where('action_code', 'USER_ACCOUNT_UPDATED')->get();
        $this->assertCount(1, $events);
        $this->assertSame($actor->id, $events->first()->actor_user_id);
        $this->assertDatabaseCount('notification_events', 1);
    }

    public function test_admin_cannot_deactivate_self_or_ictu_and_other_roles_cannot_deactivate(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $ictu = $this->actor(AccessClassification::IctuMaintainer);
        $borrower = $this->actor(AccessClassification::BorrowerOnly);
        $this->actingAs($admin)->patch(route('administration.users.deactivate', $admin))->assertForbidden();
        $this->patch(route('administration.users.deactivate', $ictu))->assertForbidden();
        foreach ([AccessClassification::SpmuOfficer, AccessClassification::BorrowerOnly] as $classification) {
            $this->actingAs($this->actor($classification))->patch(route('administration.users.deactivate', $borrower))->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['account_status' => 'INACTIVE']);
        $this->assertDatabaseMissing('audit_events', ['action_code' => 'USER_ACCOUNT_UPDATED']);
    }

    public function test_locked_target_scope_is_rechecked_before_deactivation(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $target = $this->actor(AccessClassification::BorrowerOnly);
        DB::table('users')->where('id', $target->id)->update(['access_classification' => 'ICTU_MAINTAINER']);
        $request = Request::create('/administration/users/'.$target->id.'/deactivate', 'PATCH');
        $request->setUserResolver(fn () => $admin);
        $this->actingAs($admin);
        try {
            app(UserAdministrationController::class)->deactivate($request, $target, app(AuditService::class), app(NotificationService::class));
            $this->fail('A stale bound target must not bypass the refreshed policy check.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }
        $this->assertSame(AccountStatus::Active, $target->fresh()->account_status);
        $this->assertDatabaseMissing('audit_events', ['action_code' => 'USER_ACCOUNT_UPDATED']);
    }

    public function test_failed_audit_rolls_back_deactivation(): void
    {
        $target = $this->actor(AccessClassification::BorrowerOnly);
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('UI deactivation audit failure')));
        $this->actingAs($this->actor(AccessClassification::SpmuHead))->patch(route('administration.users.deactivate', $target))->assertStatus(500);
        $this->assertSame(AccountStatus::Active, $target->fresh()->account_status);
        $this->assertDatabaseCount('notification_events', 0);
    }

    public function test_external_deactivation_keeps_registration_and_null_cspc_assignment(): void
    {
        $user = User::factory()->create(['employee_no' => null, 'employment_type' => null, 'organizational_unit_id' => null]);
        $registration = BorrowerRegistration::create(['user_id' => $user->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'Synthetic External Agency', 'status' => 'APPROVED']);
        $this->actingAs($this->actor(AccessClassification::SpmuHead))->patch(route('administration.users.deactivate', $user))->assertRedirect();
        $this->assertDatabaseHas('borrower_registrations', ['id' => $registration->id, 'user_id' => $user->id, 'status' => 'APPROVED']);
        $this->assertNull($user->fresh()->organizational_unit_id);
    }

    public function test_admin_navigation_uses_request_records_and_sao_retains_operations(): void
    {
        $admin = $this->actor(AccessClassification::SpmuHead);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Release &amp; Return', false)
            ->assertDontSee('href="'.route('custody.index').'"', false)
            ->assertSee(route('requests.index', ['kpi' => 'active_custodies']), false);
        $this->get(route('custody.index'))->assertRedirect(route('requests.index'));
        $this->get(route('custody.release.index'))->assertForbidden();
        $this->get(route('custody.return.index'))->assertForbidden();
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))->get(route('dashboard'))->assertOk()
            ->assertSee('href="'.route('custody.release.index').'"', false)
            ->assertSee('href="'.route('custody.return.index').'"', false);
        $this->get(route('custody.release.index'))->assertOk();
        $this->get(route('custody.return.index'))->assertOk();
        $this->actingAs($this->actor(AccessClassification::BorrowerOnly))->get(route('dashboard'))->assertOk()->assertSee('My Borrowings');
    }

    public function test_sao_filters_compact_records_and_opens_protected_details(): void
    {
        $employee = User::factory()->create(['full_name' => 'UI Employee Applicant']);
        $registration = BorrowerRegistration::create(['user_id' => $employee->id, 'registration_type' => 'EMPLOYEE', 'status' => 'PENDING']);
        $external = User::factory()->create(['full_name' => 'UI External Applicant', 'organizational_unit_id' => null]);
        BorrowerRegistration::create(['user_id' => $external->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'UI Agency', 'status' => 'REJECTED']);
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))->get(route('registrations.index', ['search' => 'UI Employee', 'type' => 'EMPLOYEE', 'status' => 'PENDING']))
            ->assertOk()->assertSee('UI Employee Applicant')->assertDontSee('UI External Applicant')->assertSee(route('registrations.show', $registration), false)->assertDontSee('Approve registration');
        $this->get(route('registrations.show', $registration))->assertOk()->assertSee('Approve registration')->assertSee('Review remarks');
        $this->actingAs($this->actor(AccessClassification::SpmuHead))->get(route('registrations.show', $registration))->assertForbidden();
        $this->actingAs($this->actor(AccessClassification::BorrowerOnly))->get(route('registrations.show', $registration))->assertForbidden();
    }

    public function test_registration_pagination_keeps_validated_filters(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $user = User::factory()->create(['full_name' => 'UI Page Applicant '.$i]);
            BorrowerRegistration::create(['user_id' => $user->id, 'registration_type' => 'EMPLOYEE', 'status' => 'PENDING']);
        }
        $response = $this->actingAs($this->actor(AccessClassification::SpmuOfficer))->get(route('registrations.index', ['search' => 'UI Page', 'type' => 'EMPLOYEE']));
        $response->assertOk();
        $this->assertSame(11, $response->viewData('registrations')->total());
        $this->assertCount(10, $response->viewData('registrations'));
        $this->assertStringContainsString('type=EMPLOYEE', $response->viewData('registrations')->nextPageUrl());
        $this->get(route('registrations.index', ['status' => 'FABRICATED']))->assertSessionHasErrors('status');
    }
}
