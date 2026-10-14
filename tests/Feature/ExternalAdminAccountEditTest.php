<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Http\Controllers\UserAdministrationController;
use App\Models\BorrowerRegistration;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailVerificationService;
use App\Services\NotificationService;
use App\Services\ProtectedFileService;
use App\Services\UserRoleAssignmentService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\ActualExternalApprovalFlow;
use Tests\Support\EvidenceFixtures;
use Tests\TestCase;

class ExternalAdminAccountEditTest extends TestCase
{
    use ActualExternalApprovalFlow, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', 'Asia/Manila'));
        config(['app.seed_demo_users' => true]);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    #[DataProvider('administrators')]
    public function test_external_form_uses_saved_reviewed_fields_without_employee_controls(string $actor): void
    {
        $target = $this->external();
        $this->as($this->administrator($actor))->get(route('administration.users.edit', $target))->assertOk()
            ->assertSeeText('Edit external borrower account')->assertSee('value="Reviewed Number Agency"', false)
            ->assertSeeText('SAO registration status')->assertSee('value="APPROVED"', false)
            ->assertDontSee('name="employee_no"', false)->assertDontSee('name="designation"', false)
            ->assertDontSee('name="division_code"', false)->assertDontSee('name="organizational_unit_id"', false)
            ->assertDontSee('name="additional_organizational_unit_ids[]"', false)
            ->assertDontSee('name="employment_type"', false)->assertDontSee('name="company_name"', false)
            ->assertDontSee('value="SPMU_HEAD"', false)->assertDontSee('value="SPMU_OFFICER"', false)
            ->assertSee('data-password-toggle', false)->assertSee('ictu-form-card', false);
    }

    public static function administrators(): array
    {
        return [['Admin'], ['ICTU']];
    }

    #[DataProvider('reviewStates')]
    public function test_basic_external_edit_preserves_review_identity_and_role_history(string $status): void
    {
        $target = $this->external($status);
        $old = $this->state();
        $response = $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'full_name' => 'Updated external contact', 'mobile_no' => '09179998888',
        ]);
        // Direct assertion avoids the framework's failing-redirect contextualizer
        // masking validation failures with an unrelated "all() on array" error.
        $this->assertFalse(session()->has('errors'), 'External contact edits must not require employee/CSPC fields.');
        $response->assertRedirect(route('administration.users.index'))->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame('Updated external contact', $target->full_name);
        $this->assertSame('09179998888', $target->mobile_no);
        $this->assertNotNull($target->email_verified_at);
        $this->assertSame($status, $target->borrowerRegistration->status->value);
        $this->assertExternalHistoryPreserved($old);
        $this->assertSame($status === 'APPROVED' ? ['BORROWER'] : [], $target->roles()->wherePivotNull('revoked_at')->pluck('role_code')->all());
        $this->assertDatabaseHas('audit_events', ['action_code' => 'USER_ACCOUNT_UPDATED', 'record_id' => $target->id]);
        if ($status !== 'APPROVED') {
            $this->as($target)->get(route('dashboard'))->assertRedirect(route('login'));
        }
    }

    public static function reviewStates(): array
    {
        return [['APPROVED'], ['PENDING'], ['REJECTED']];
    }

    #[DataProvider('reviewStates')]
    public function test_employee_fields_cannot_fabricate_assignments_or_roles_for_an_external_account(string $status): void
    {
        $target = $this->external($status);
        $unit = OrganizationalUnit::where('unit_code', 'CCS')->sole();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'email' => 'forged.external@cspc.edu.ph',
            'employee_no' => 'FORGED-EXTERNAL-01', 'designation' => 'Administrative Aide',
            'employment_type' => 'STAFF', 'employment_status' => 'FULL_TIME',
            'division_code' => $unit->divisionCode(), 'organizational_unit_id' => $unit->id,
        ])->assertSessionHasErrors(['employee_no', 'organizational_unit_id']);
        $this->assertSame($before, $this->state());
    }

    #[DataProvider('sealedFields')]
    public function test_posted_review_employee_unit_or_role_overrides_are_rejected(string $field, mixed $value): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), $field => $value,
        ])->assertSessionHasErrors($field);
        $this->assertSame($before, $this->state());
    }

    public static function sealedFields(): array
    {
        return [
            ['company_name', 'Different unreviewed company'], ['registration_type', 'EMPLOYEE'], ['registration_status', 'APPROVED'],
            ['status', 'APPROVED'], ['valid_id_file_id', 1], ['reviewed_by_user_id', 3], ['reviewed_at', '2026-10-13'],
            ['review_remarks', 'Fake approval'], ['email_verified_at', '2026-10-13'], ['division_code', 'ACADEMIC'],
            ['office_unit', 'CCS'], ['new_organizational_unit_name', 'Invented external unit'],
            ['additional_organizational_unit_ids', [1]], ['roles', ['SPMU']], ['employee_no', null],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_basic_external_edit_cannot_convert_registration_into_a_staff_account(string $classification): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'access_classification' => $classification,
        ])->assertSessionHasErrors('access_classification');
        $this->assertSame($before, $this->state());
    }

    public static function staffRoles(): array
    {
        return [['SPMU_HEAD'], ['SPMU_OFFICER'], ['ICTU_MAINTAINER']];
    }

    public function test_unchanged_external_form_keeps_password_review_and_pivot_rows_without_audit_noise(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), $this->accountPayload($target) + ['password' => '', 'password_confirmation' => ''])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'No account changes were detected.');
        $this->assertSame($before, $this->state());
    }

    public function test_changed_external_mailbox_requires_otp_without_reapproving_or_granting_roles(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'email' => '  Changed.External@Agency.test  ',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('changed.external@agency.test', $target->fresh()->email);
        $this->assertNull($target->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $target->id, 'email' => 'changed.external@agency.test']);
        $this->assertExternalHistoryPreserved($before);
        $this->as($target->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_mailbox_case_and_whitespace_normalization_does_not_reset_verification(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'email' => '  '.strtoupper($target->email).'  ',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'No account changes were detected.');
        $this->assertSame($before, $this->state());
    }

    #[DataProvider('acceptedPasswords')]
    public function test_password_change_is_confirmed_hashed_and_not_exposed_in_audit(string $password): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $target->fresh()->password));
        $events = DB::table('audit_events')->where('action_code', 'USER_ACCOUNT_UPDATED')->get()->toJson();
        $this->assertStringNotContainsString($password, $events);
        $this->assertStringNotContainsString($target->fresh()->password, $events);
        $this->assertExternalHistoryPreserved($before);
    }

    public static function acceptedPasswords(): array
    {
        return [['New-External-Password-2026!'], ['abcdefghij1!'], ['éééééééééé1!']];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_external_input_changes_nothing(string $field, mixed $value, string $error): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), $field => $value,
        ])->assertSessionHasErrors($error);
        $this->assertSame($before, $this->state());
    }

    public static function invalidFields(): array
    {
        return [['full_name', '', 'full_name'], ['full_name', str_repeat('n', 256), 'full_name'],
            ['mobile_no', str_repeat('1', 31), 'mobile_no'], ['email', 'invalid-mailbox', 'email'],
            ['email', 'spmu-head@spmu.test', 'email'], ['account_status', 'APPROVED', 'account_status'],
            ['password', 'short', 'password']];
    }

    #[DataProvider('accountStates')]
    public function test_external_target_account_state_is_editable_without_changing_review(string $status): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'account_status' => $status,
        ])->assertSessionHasNoErrors();
        $this->assertSame($status, $target->fresh()->account_status->value);
        $this->assertExternalHistoryPreserved($before);
    }

    public static function accountStates(): array
    {
        return [['INACTIVE'], ['SUSPENDED']];
    }

    #[DataProvider('unauthorizedActors')]
    public function test_unauthorized_actor_cannot_view_or_edit_external_accounts(string $actor): void
    {
        $target = $this->external();
        $before = $this->state();
        if ($actor !== 'guest') {
            $user = $actor === 'SAO' ? $this->sao() : User::where('email', 'borrower@spmu.test')->sole();
            $this->as($user);
        }
        $get = $this->get(route('administration.users.edit', $target));
        $put = $this->put(route('administration.users.update', $target), $this->accountPayload($target));
        if ($actor === 'guest') {
            $get->assertRedirect(route('login'));
            $put->assertRedirect(route('login'));
        } else {
            $get->assertForbidden();
            $put->assertForbidden();
        }
        $this->assertSame($before, $this->state());
    }

    public static function unauthorizedActors(): array
    {
        return [['guest'], ['SAO'], ['Borrower']];
    }

    public function test_stale_actor_disabled_after_binding_is_denied_before_account_write(): void
    {
        $target = $this->external();
        $actor = $this->headUser();
        $request = Request::create('/administration/users/'.$target->id, 'PUT', [...$this->accountPayload($target), 'full_name' => 'Should not save']);
        $request->setUserResolver(fn () => $actor);
        $this->as($actor);
        DB::table('users')->where('id', $actor->id)->update(['account_status' => 'INACTIVE']);
        $before = $this->state();
        try {
            $this->callUpdate($request, $target);
            $this->fail('Fresh actor permission must be checked before writing.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_actor_with_revoked_portal_assignment_cannot_administer_external_account(): void
    {
        $target = $this->external();
        $actor = $this->headUser();
        $request = Request::create('/administration/users/'.$target->id, 'PUT', [...$this->accountPayload($target), 'full_name' => 'Should not save']);
        $request->setUserResolver(fn () => $actor);
        $this->as($actor);
        DB::table('user_roles')->where('user_id', $actor->id)->update(['revoked_at' => now()]);
        $before = $this->state();
        try {
            $this->callUpdate($request, $target);
            $this->fail('A classification without its active portal assignment cannot authorize Admin editing.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_unverified_admin_cannot_view_or_edit_external_account(): void
    {
        $target = $this->external();
        $actor = $this->headUser();
        $actor->forceFill(['email_verified_at' => null])->save();
        $before = $this->state();
        $this->as($actor)->get(route('administration.users.edit', $target))->assertRedirect(route('login'));
        $this->put(route('administration.users.update', $target), $this->accountPayload($target))->assertRedirect(route('login'));
        $this->assertSame($before, $this->state());
    }

    public function test_external_boundary_fields_are_accepted_without_internal_assignments(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'full_name' => str_repeat('n', 255), 'mobile_no' => str_repeat('1', 30),
        ])->assertSessionHasNoErrors();
        $this->assertSame(255, strlen($target->fresh()->full_name));
        $this->assertSame(30, strlen($target->fresh()->mobile_no));
        $this->assertExternalHistoryPreserved($before);
    }

    public function test_changed_mailbox_delivery_failure_keeps_committed_contact_edit_and_unverified_email(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->mock(EmailVerificationService::class)->shouldReceive('send')->once()->andReturnFalse();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'email' => 'delivery.failure@agency.test',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Account updated and its changed email is unverified. OTP delivery failed; contact ICTU for mail assistance or request a new OTP on the account verification page.');
        $this->assertSame('delivery.failure@agency.test', $target->fresh()->email);
        $this->assertNull($target->fresh()->email_verified_at);
        $this->assertExternalHistoryPreserved($before);
        $this->assertDatabaseHas('audit_events', ['record_id' => $target->id, 'action_code' => 'USER_EMAIL_REVERIFICATION_DELIVERY_FAILED']);
    }

    public function test_registration_type_change_during_lock_is_revalidated_and_rolled_back(): void
    {
        $target = $this->external();
        $before = $this->state();
        $changed = false;
        DB::listen(function ($query) use (&$changed, $target): void {
            if (! $changed && DB::transactionLevel() > 1 && str_contains(strtolower($query->sql), 'borrower_registrations')
                && str_starts_with(strtolower($query->sql), 'select')) {
                $changed = true;
                DB::table('borrower_registrations')->where('user_id', $target->id)->update(['registration_type' => 'EMPLOYEE']);
            }
        });
        $this->as($this->headUser())->put(route('administration.users.update', $target), $this->accountPayload($target))->assertSessionHasErrors('employee_no');
        $this->assertTrue($changed);
        $this->assertSame($before, $this->state());
    }

    public function test_inconsistent_external_staff_record_is_not_silently_reclassified_or_repaired(): void
    {
        $target = $this->external();
        $target->update(['access_classification' => AccessClassification::SpmuHead]);
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'access_classification' => 'BORROWER_ONLY',
        ])->assertSessionHasErrors('account');
        $this->assertSame($before, $this->state());
    }

    public function test_registration_type_changed_after_binding_cannot_apply_external_fields_to_employee_account(): void
    {
        $target = $this->external();
        $target->load('borrowerRegistration');
        $actor = $this->headUser();
        $request = Request::create('/administration/users/'.$target->id, 'PUT', [...$this->accountPayload($target), 'full_name' => 'Should not save']);
        $request->setUserResolver(fn () => $actor);
        $this->as($actor);
        DB::table('borrower_registrations')->where('user_id', $target->id)->update(['registration_type' => 'EMPLOYEE']);
        $before = $this->state();
        try {
            $this->callUpdate($request, $target);
            $this->fail('Persisted registration type must be rechecked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_no', $exception->errors());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_audit_failure_rolls_back_external_contact_password_and_verification_fields(): void
    {
        $target = $this->external();
        $before = $this->state();
        $this->mock(AuditService::class)->shouldReceive('record')->withArgs(fn (...$args) => $args[0] === 'USER_ACCOUNT_UPDATED')
            ->andThrow(new \RuntimeException('Synthetic external account audit failure'));
        $this->as($this->headUser())->put(route('administration.users.update', $target), [
            ...$this->accountPayload($target), 'full_name' => 'Failed contact edit', 'email' => 'failed.contact@agency.test',
            'password' => 'Failed-Password-2026!', 'password_confirmation' => 'Failed-Password-2026!',
        ])->assertStatus(500);
        $this->assertSame($before, $this->state());
    }

    #[DataProvider('evidenceStates')]
    public function test_contact_edit_does_not_replace_or_claim_to_repair_missing_or_corrupt_reviewed_id(string $kind): void
    {
        $target = $this->external();
        $file = $target->borrowerRegistration->validIdFile;
        if ($kind === 'missing') {
            Storage::disk('local')->delete($file->storage_path);
        } else {
            Storage::disk('local')->put($file->storage_path, 'Synthetic damaged old ID');
        }
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [...$this->accountPayload($target), 'mobile_no' => '09171112222'])->assertSessionHasNoErrors();
        $this->assertExternalHistoryPreserved($before);
    }

    public static function evidenceStates(): array
    {
        return [['missing'], ['corrupt']];
    }

    public function test_external_account_edit_keeps_corrected_approved_requests_and_all_private_evidence_unchanged(): void
    {
        $request = $this->ready();
        $this->approve($request)->assertSessionHasNoErrors();
        $target = $request->borrower;
        $before = $this->state();
        $this->as($this->headUser())->put(route('administration.users.update', $target), [...$this->accountPayload($target), 'full_name' => 'Updated account display name'])->assertSessionHasNoErrors();
        $this->assertExternalHistoryPreserved($before);
        $this->assertSame('Reviewed External Agency', $request->fresh()->currentVersion->external_company_name);
    }

    private function external(string $status = 'APPROVED'): User
    {
        $user = User::factory()->create(['email' => 'external.edit@agency.test', 'employee_no' => null, 'designation' => null,
            'employment_type' => null, 'employment_status' => null, 'organizational_unit_id' => null, 'access_classification' => AccessClassification::BorrowerOnly]);
        $file = app(ProtectedFileService::class)->storeBytes(EvidenceFixtures::signatureBytes('Synthetic external ID'), 'external-edit-fixtures', 'synthetic-valid-id.png', 'image/png', 'png', 'BORROWER_REGISTRATION_ID', $user->id);
        $registration = BorrowerRegistration::create(['user_id' => $user->id, 'registration_type' => 'EXTERNAL', 'company_name' => 'Reviewed Number Agency', 'valid_id_file_id' => $file->id, 'status' => $status]);
        if ($status !== 'PENDING') {
            $registration->forceFill(['reviewed_by_user_id' => $this->sao()->id, 'reviewed_at' => now()->subDay(), 'review_remarks' => 'Existing synthetic review'])->save();
        }
        if ($status === 'APPROVED') {
            app(UserRoleAssignmentService::class)->synchronize($user, AccessClassification::BorrowerOnly, $this->sao()->id);
        }

        return $user->fresh();
    }

    private function administrator(string $actor): User
    {
        return $actor === 'Admin' ? $this->headUser() : User::where('email', 'ictu@spmu.test')->sole();
    }

    private function accountPayload(User $user): array
    {
        return ['full_name' => $user->full_name, 'email' => $user->email, 'mobile_no' => $user->mobile_no,
            'account_status' => $user->account_status->value, 'access_classification' => $user->access_classification->value];
    }

    private function callUpdate(Request $request, User $target): void
    {
        app(UserAdministrationController::class)->update($request, $target, app(AuditService::class), app(UserRoleAssignmentService::class), app(NotificationService::class));
    }

    private function state(): array
    {
        $rows = [];
        foreach (['users', 'borrower_registrations', 'user_roles', 'user_organizational_units', 'organizational_units', 'stored_files', 'user_signatures',
            'signature_snapshots', 'borrowing_requests', 'request_versions', 'request_items', 'request_supporting_documents', 'approval_steps',
            'allocations', 'custody_transactions', 'generated_documents', 'audit_events', 'notification_events', 'notification_deliveries', 'email_verification_otps'] as $table) {
            $rows[$table] = DB::table($table)->orderBy($table === 'user_roles' ? 'user_id' : 'id')->get()->map(fn ($row) => (array) $row)->all();
        }
        $files = [];
        foreach (Storage::disk('local')->allFiles() as $path) {
            $files[$path] = hash('sha256', Storage::disk('local')->get($path));
        } ksort($files);

        return ['rows' => $rows, 'files' => $files];
    }

    private function assertExternalHistoryPreserved(array $old): void
    {
        $current = $this->state();
        foreach ($old['rows'] as $table => $rows) {
            if (in_array($table, ['users', 'audit_events', 'notification_events', 'notification_deliveries', 'email_verification_otps'], true)) {
                continue;
            }
            $this->assertSame($rows, $current['rows'][$table], $table.' existing history changed');
        }
        $this->assertSame($old['files'], $current['files']);
    }
}
