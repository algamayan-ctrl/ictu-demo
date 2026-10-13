<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AuditEvent;
use App\Models\BorrowerRegistration;
use App\Models\NotificationDelivery;
use App\Models\OrganizationalUnit;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\Provider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BorrowerSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seed_demo_users' => true, 'services.institutional_email.allowed_domains' => 'cspc.edu.ph,spmu.test']);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    public function test_public_form_and_sign_in_link_expose_both_registration_paths(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('CSPC Employee')->assertSee('External Borrower')
            ->assertDontSee('name="access_classification"', false)->assertDontSee('Walk-in');
        $this->get(route('login'))->assertOk()->assertSee(route('register'), false);
    }

    public function test_employee_registration_is_pending_borrower_only_without_role_or_unit_grants(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $user = $registration->user;

        $this->assertSame('PENDING', $registration->status->value);
        $this->assertSame('BORROWER_ONLY', $user->access_classification->value);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->mayBorrow());
        $this->assertSame(0, $user->roles()->count());
        $this->assertDatabaseMissing('user_organizational_units', ['user_id' => $user->id]);
        $this->assertDatabaseHas('email_verification_otps', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'BORROWER_REGISTRATION_CREATED', 'record_id' => $registration->id]);
        $sao = $this->actor(AccessClassification::SpmuOfficer);
        $this->assertFalse(NotificationDelivery::query()->where('recipient_user_id', $sao->id)
            ->whereHas('event', fn ($query) => $query->where('event_code', 'BORROWER_REGISTRATION_SUBMITTED'))->exists());
        $this->assertStringNotContainsString('Registration!Pass2026', AuditEvent::query()->latest('id')->first()->toJson());
    }

    public function test_external_registration_stores_private_id_without_invented_employee_or_office(): void
    {
        $this->register($this->externalPayload());
        $registration = BorrowerRegistration::query()->firstOrFail();
        $user = $registration->user;

        $this->assertSame('EXTERNAL', $registration->registration_type->value);
        $this->assertSame('Example Agency', $registration->company_name);
        $this->assertNull($user->employee_no);
        $this->assertNull($user->employment_type);
        $this->assertNull($user->organizational_unit_id);
        $this->assertSame('BORROWER_REGISTRATION_ID', $registration->validIdFile->classification);
        Storage::disk('local')->assertExists($registration->validIdFile->storage_path);
        $this->assertSame($user->id, $registration->validIdFile->uploaded_by_user_id);
    }

    public function test_multiple_external_accounts_can_have_null_employee_fields(): void
    {
        $this->register($this->externalPayload());
        $second = $this->externalPayload();
        $second['email'] = 'another.external@example.gov.ph';
        $this->register($second);
        $this->assertSame(2, User::query()->whereNull('employee_no')->whereNull('employment_type')->count());
        $this->assertDatabaseCount('borrower_registrations', 2);
    }

    public function test_existing_mixed_case_email_cannot_be_registered_again(): void
    {
        User::factory()->create(['email' => 'Self.Employee@CSPC.EDU.PH']);
        $this->post(route('register.store'), $this->employeePayload())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_invalid_password_confirmation_and_phone_are_rejected(): void
    {
        $data = $this->employeePayload();
        $data['password_confirmation'] = 'Different!Pass2026';
        $data['mobile_no'] = 'not-a-phone';
        $this->post(route('register.store'), $data)->assertSessionHasErrors(['password', 'mobile_no']);
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_structured_email_input_is_a_validation_error_without_registration(): void
    {
        $this->post(route('register.store'), [...$this->employeePayload(), 'email' => ['invalid@example.com']])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->assertDatabaseMissing('users', ['employee_no' => 'SELF-REG-01']);
    }

    public function test_valid_pdf_id_is_accepted(): void
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Synthetic registration ID');
        $data = $this->externalPayload();
        $data['valid_id'] = UploadedFile::fake()->createWithContent('valid-id.pdf', $pdf->Output('S'));
        $this->register($data);
        $this->assertSame('application/pdf', BorrowerRegistration::query()->firstOrFail()->validIdFile->mime_type);
    }

    public static function forgedFields(): array
    {
        return [
            ['access_classification', 'SPMU_HEAD'], ['role', 'ICTU'], ['account_status', 'ACTIVE'],
            ['registration_status', 'APPROVED'], ['email_verified_at', '2026-10-12'], ['reviewed_by_user_id', 1],
        ];
    }

    #[DataProvider('forgedFields')]
    public function test_public_registration_rejects_privileged_fields(string $field, mixed $value): void
    {
        $this->post(route('register.store'), array_merge($this->employeePayload(), [$field => $value]))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('users', ['email' => 'self.employee@cspc.edu.ph']);
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_duplicate_email_and_employee_number_do_not_create_second_application(): void
    {
        $this->register();
        $this->post(route('register.store'), array_merge($this->employeePayload(), ['_action_token' => (string) Str::uuid()]))
            ->assertSessionHasErrors(['email', 'employee_no']);
        $this->assertDatabaseCount('borrower_registrations', 1);
    }

    public function test_duplicate_submission_does_not_flash_password_fields(): void
    {
        $data = array_merge($this->employeePayload(), ['_action_token' => (string) Str::uuid()]);
        $this->register($data);
        $this->post(route('register.store'), $data)->assertRedirect();
        $this->assertNull(session()->get('_old_input.password'));
        $this->assertNull(session()->get('_old_input.password_confirmation'));
        $this->assertDatabaseCount('borrower_registrations', 1);
    }

    public function test_invalid_employee_domain_or_division_is_rejected(): void
    {
        $data = $this->employeePayload();
        $data['email'] = 'employee@example.gov.ph';
        $this->post(route('register.store'), $data)->assertSessionHasErrors('email');
        $data = $this->employeePayload();
        $data['division_code'] = OrganizationalUnit::query()->where('unit_code', 'SPMU')->firstOrFail()->divisionCode();
        $this->post(route('register.store'), $data)->assertSessionHasErrors('organizational_unit_id');
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_external_registration_requires_company_and_readable_id(): void
    {
        $data = $this->externalPayload();
        unset($data['company_name'], $data['valid_id']);
        $this->post(route('register.store'), $data)->assertSessionHasErrors(['company_name', 'valid_id']);
        $data = $this->externalPayload();
        $data['valid_id'] = UploadedFile::fake()->createWithContent('id.pdf', '%PDF-1.4 invalid document');
        $this->post(route('register.store'), $data)->assertSessionHasErrors('valid_id');
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_executable_extension_and_oversized_upload_are_rejected(): void
    {
        $data = $this->externalPayload();
        $data['valid_id'] = UploadedFile::fake()->image('id.php');
        $this->post(route('register.store'), $data)->assertSessionHasErrors('valid_id');
        $data['valid_id'] = UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf');
        $this->post(route('register.store'), $data)->assertSessionHasErrors('valid_id');
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_failed_storage_rolls_back_user_and_file_metadata(): void
    {
        $count = StoredFile::query()->count();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->andReturn(false);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('files')->andReturn([]);
        $disk->shouldReceive('delete')->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->post(route('register.store'), $this->externalPayload())->assertSessionHasErrors('valid_id');
        $this->assertDatabaseMissing('users', ['email' => 'external@example.gov.ph']);
        $this->assertSame($count, StoredFile::query()->count());
    }

    public function test_audit_failure_rolls_back_registration_and_removes_uploaded_bytes(): void
    {
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('Test audit failure')));
        $this->post(route('register.store'), $this->externalPayload())->assertStatus(500);
        $this->assertDatabaseMissing('users', ['email' => 'external@example.gov.ph']);
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('registration-ids'));
    }

    public function test_mail_failure_keeps_pending_registration_for_recovery(): void
    {
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('Test transport failure'));
        $this->register();
        $this->assertDatabaseCount('borrower_registrations', 1);
        $this->assertDatabaseCount('email_verification_otps', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->assertNull(BorrowerRegistration::query()->firstOrFail()->submitted_for_review_at);
    }

    public function test_otp_verification_does_not_approve_registration_or_allow_portal_access(): void
    {
        $this->register();
        $user = BorrowerRegistration::query()->firstOrFail()->user;
        DB::table('email_verification_otps')->where('user_id', $user->id)->update(['code_hash' => Hash::make('123456')]);
        $this->post(route('account.verification.verify'), ['email' => $user->email, 'otp' => '123456'])
            ->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('borrower_registrations', ['user_id' => $user->id, 'status' => 'PENDING']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Registration!Pass2026'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->actingAs($user->fresh())->post(route('requests.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_google_sign_in_cannot_bypass_pending_registration(): void
    {
        $this->register();
        $user = BorrowerRegistration::query()->firstOrFail()->user;
        $user->markInstitutionalEmailAsVerified();
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test', 'services.google.redirect' => 'http://localhost/auth/google/callback', 'services.google.allowed_domains' => '']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn((new GoogleUser)->map(['id' => 'self-test', 'email' => $user->email]));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_sao_approval_grants_one_borrower_role_and_preserves_independent_email_gate(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $sao = $this->actor(AccessClassification::SpmuOfficer);
        $this->actingAs($sao)->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertForbidden();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($sao)->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])
            ->assertRedirect(route('registrations.index'))->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $registration->fresh()->status->value);
        $this->assertSame($sao->id, $registration->fresh()->reviewed_by_user_id);
        $this->assertSame(['BORROWER'], $registration->user->roles()->wherePivotNull('revoked_at')->pluck('role_code')->all());
        $this->assertDatabaseHas('user_organizational_units', ['user_id' => $registration->user_id, 'assigned_by_user_id' => $sao->id]);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'BORROWER_REGISTRATION_APPROVED', 'actor_user_id' => $sao->id]);
        $this->post(route('logout'));
        // Re-invalidating an approved mailbox must still block credential login.
        $registration->user->forceFill(['email_verified_at' => null])->save();
        $this->post(route('login.store'), ['email' => $registration->user->email, 'password' => 'Registration!Pass2026'])
            ->assertRedirect(route('account.verification.show', ['email' => $registration->user->email]));
        $this->assertGuest();
    }

    public function test_approved_verified_external_borrower_can_sign_in_and_open_dashboard(): void
    {
        $this->register($this->externalPayload());
        $registration = BorrowerRegistration::query()->firstOrFail();
        $this->post(route('login.store'), ['email' => $registration->user->email, 'password' => 'Registration!Pass2026'])
            ->assertRedirect(route('account.verification.show', ['email' => $registration->user->email]))
            ->assertSessionHas('status', 'Your account is pending verification. Enter the 6-digit OTP sent to your registered email address.');
        $this->assertGuest();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $registration->user->email, 'password' => 'Registration!Pass2026'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public static function nonReviewers(): array
    {
        return [['BORROWER_ONLY'], ['SPMU_HEAD'], ['ICTU_MAINTAINER']];
    }

    #[DataProvider('nonReviewers')]
    public function test_only_sao_may_review_self_registration(string $classification): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($this->actor(AccessClassification::from($classification)))
            ->get(route('registrations.index'))->assertForbidden();
        $this->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertForbidden();
        $this->assertSame('PENDING', $registration->fresh()->status->value);
    }

    public function test_duplicate_decisions_do_not_reverse_approval_or_duplicate_audit(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED', '_action_token' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();
        $this->post(route('registrations.decide', $registration), ['decision' => 'REJECTED', 'remarks' => 'Second decision', '_action_token' => (string) Str::uuid()])
            ->assertSessionHasErrors('registration');
        $this->assertSame('APPROVED', $registration->fresh()->status->value);
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'BORROWER_REGISTRATION_APPROVED')->count());
    }

    public function test_rejection_requires_remarks_and_blocks_verified_applicant(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'REJECTED'])->assertSessionHasErrors('remarks');
        $this->post(route('registrations.decide', $registration), ['decision' => 'REJECTED', 'remarks' => 'Details could not be verified.'])
            ->assertRedirect(route('registrations.index'));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $registration->user->email, 'password' => 'Registration!Pass2026'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertSame('REJECTED', $registration->fresh()->status->value);
        $this->assertFalse($registration->user->mayBorrow());
    }

    public function test_missing_or_corrupted_id_blocks_external_approval(): void
    {
        $this->register($this->externalPayload());
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $path = $registration->validIdFile->storage_path;
        Storage::disk('local')->put($path, 'corrupted bytes');
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertSessionHasErrors('registration');
        Storage::disk('local')->delete($path);
        $this->post(route('registrations.decide', $registration), ['decision' => 'APPROVED', '_action_token' => (string) Str::uuid()])
            ->assertSessionHasErrors('registration');
        $this->assertSame('PENDING', $registration->fresh()->status->value);
        $this->assertSame(0, $registration->user->roles()->count());
    }

    public function test_registration_id_is_private_to_reviewer_and_approved_owner(): void
    {
        $this->register($this->externalPayload());
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $file = $registration->validIdFile;
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))->get(route('files.show', $file))->assertOk();
        foreach ([AccessClassification::SpmuHead, AccessClassification::IctuMaintainer, AccessClassification::BorrowerOnly] as $classification) {
            $this->actingAs($this->actor($classification))->get(route('files.show', $file))->assertForbidden();
        }
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->actingAs($registration->user->fresh())->get(route('files.show', $file))->assertOk();
    }

    public function test_approval_does_not_reactivate_a_disabled_account(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $registration->user->update(['account_status' => 'INACTIVE']);
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVE', $registration->user->fresh()->account_status->value);
    }

    public static function unavailableApprovalConfiguration(): array
    {
        return [['organizational_units'], ['roles']];
    }

    #[DataProvider('unavailableApprovalConfiguration')]
    public function test_unavailable_assignment_configuration_keeps_registration_pending(string $table): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $query = DB::table($table);
        $table === 'roles'
            ? $query->where('role_code', 'BORROWER')
            : $query->where('id', $registration->user->organizational_unit_id);
        $query->update(['active' => false]);

        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])
            ->assertSessionHasErrors('registration');
        $this->assertSame('PENDING', $registration->fresh()->status->value);
        $this->assertSame(0, $registration->user->roles()->count());
        $this->assertDatabaseMissing('audit_events', ['action_code' => 'BORROWER_REGISTRATION_APPROVED']);
    }

    public function test_approval_mail_failure_preserves_decision_and_failed_delivery_evidence(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('Test approval transport failure'));

        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])
            ->assertSessionHasNoErrors();
        $this->assertSame('APPROVED', $registration->fresh()->status->value);
        $this->assertSame(1, $registration->user->roles()->wherePivotNull('revoked_at')->count());
        $this->assertTrue(NotificationDelivery::query()->where('channel', 'EMAIL')->where('delivery_status', 'FAILED')
            ->whereHas('event', fn ($query) => $query->where('event_code', 'BORROWER_REGISTRATION_APPROVED'))->exists());
    }

    public function test_approval_audit_failure_rolls_back_decision_and_grants(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('Test approval audit failure')));

        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))
            ->post(route('registrations.decide', $registration), ['decision' => 'APPROVED'])->assertStatus(500);
        $this->assertSame('PENDING', $registration->fresh()->status->value);
        $this->assertNull($registration->fresh()->reviewed_by_user_id);
        $this->assertSame(0, $registration->user->roles()->count());
        $this->assertDatabaseMissing('user_organizational_units', ['user_id' => $registration->user_id]);
    }

    public function test_sao_queue_renders_only_verified_registration_details_and_history(): void
    {
        $this->register();
        $registration = BorrowerRegistration::query()->firstOrFail();
        $this->actingAs($this->actor(AccessClassification::SpmuOfficer))->get(route('registrations.index'))
            ->assertOk()->assertDontSee('Self Registered Employee');
        $this->get(route('registrations.show', $registration))->assertForbidden();
        $registration->user->markInstitutionalEmailAsVerified();
        $this->get(route('registrations.index'))->assertOk()->assertSee('Self Registered Employee')->assertSee('Email verified');
        $this->get(route('registrations.show', $registration))->assertOk()->assertSee('Approve registration');
    }

    public function test_rollback_refuses_to_destroy_registration_history(): void
    {
        $this->register();
        $migration = require database_path('migrations/2026_10_12_000001_add_borrower_self_registration.php');
        try {
            $migration->down();
            $this->fail('Rollback must not discard registration history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('preserved', $exception->getMessage());
        }
        $this->assertDatabaseCount('borrower_registrations', 1);
    }

    private function register(?array $data = null): void
    {
        $data ??= $this->employeePayload();
        $this->post(route('register.store'), $data)->assertRedirect(route('account.verification.show', ['email' => $data['email']]))
            ->assertSessionHasNoErrors();
    }

    private function employeePayload(): array
    {
        $unit = OrganizationalUnit::query()->where('unit_code', 'CCS')->firstOrFail();

        return ['registration_type' => 'EMPLOYEE', 'full_name' => 'Self Registered Employee',
            'email' => 'self.employee@cspc.edu.ph', 'mobile_no' => '09171234567',
            'employee_no' => 'SELF-REG-01', 'designation' => 'Faculty Member',
            'division_code' => $unit->divisionCode(), 'organizational_unit_id' => $unit->id,
            'password' => 'Registration!Pass2026', 'password_confirmation' => 'Registration!Pass2026'];
    }

    private function externalPayload(): array
    {
        return ['registration_type' => 'EXTERNAL', 'full_name' => 'External Applicant',
            'email' => 'external@example.gov.ph', 'mobile_no' => '09171234567',
            'company_name' => 'Example Agency', 'valid_id' => UploadedFile::fake()->image('valid-id.png'),
            'password' => 'Registration!Pass2026', 'password_confirmation' => 'Registration!Pass2026'];
    }

    private function actor(AccessClassification $classification): User
    {
        return User::query()->where('access_classification', $classification->value)->firstOrFail();
    }
}
