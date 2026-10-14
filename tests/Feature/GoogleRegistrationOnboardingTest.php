<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\BorrowerRegistration;
use App\Models\OauthIdentity;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleRegistrationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seed_demo_users' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback',
            'services.google.allowed_domains' => '', 'services.institutional_email.allowed_domains' => 'cspc.edu.ph,spmu.test']);
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Storage::fake('local');
    }

    public static function newAccounts(): array
    {
        return ['employee' => ['applicant@cspc.edu.ph', 'EMPLOYEE']];
    }

    /** External Borrowers and CSPC students never register through Google. */
    public static function refusedAccounts(): array
    {
        return ['gmail' => ['applicant@gmail.com'], 'yahoo Google account' => ['applicant@yahoo.com'],
            'government Google account' => ['applicant@gov.edu.ph'], 'CSPC student address' => ['applicant@my.cspc.edu.ph']];
    }

    #[DataProvider('refusedAccounts')]
    public function test_external_and_student_google_identities_cannot_register_through_google(string $email): void
    {
        $count = User::count();
        $this->fakeCallback($email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertNull(session('google_registration'));
        $this->assertSame($count, User::count());
        $this->assertDatabaseCount('oauth_identities', 0);
        $this->assertGuest();

        /* A hand-posted Google-sourced External application is refused at the backend too. */
        $this->post(route('register.store'), $this->payload($email, 'EXTERNAL'))->assertSessionHasErrors();
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_google_employee_registration_follows_the_configured_institutional_domains(): void
    {
        config(['services.institutional_email.allowed_domains' => 'cspc.edu.ph,spmu.test']);
        $this->fakeCallback('applicant@spmu.test');

        $this->get(route('auth.google.callback'))->assertRedirect(route('register', ['source' => 'google']));

        $this->assertSame('EMPLOYEE', session('google_registration.registration_type'));
        $this->assertGuest();
    }

    public function test_a_student_domain_never_becomes_an_employee_domain_even_if_listed(): void
    {
        config(['services.institutional_email.allowed_domains' => 'cspc.edu.ph,my.cspc.edu.ph']);
        $this->fakeCallback('applicant@my.cspc.edu.ph');

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');

        $this->assertNull(session('google_registration'));
        $this->assertGuest();
    }

    private function fakeCallback(string $email, bool $verified = true): void
    {
        $profile = (new GoogleUser)->map(['id' => 'new-provider-subject', 'email' => $email, 'name' => 'Google Applicant']);
        $profile->setRaw(['email_verified' => $verified]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($profile);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    private function payload(string $email = 'applicant@gmail.com', string $type = 'EXTERNAL'): array
    {
        $data = ['registration_source' => 'google', 'registration_type' => $type, 'full_name' => 'Google Applicant',
            'email' => $email, 'mobile_no' => '09171234567', 'password' => 'Registration!Pass2026', 'password_confirmation' => 'Registration!Pass2026'];
        if ($type === 'EMPLOYEE') {
            $unit = OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail();
            return $data + ['employee_no' => 'GOOGLE-EMP-001', 'designation' => 'Faculty', 'division_code' => $unit->divisionCode(), 'organizational_unit_id' => $unit->id];
        }
        return $data + ['company_name' => 'Reviewed Example Agency', 'valid_id' => UploadedFile::fake()->image('valid-id.png')];
    }

    private function sao(): User
    {
        return User::where('access_classification', AccessClassification::SpmuOfficer)->firstOrFail();
    }

    #[DataProvider('newAccounts')]
    public function test_verified_google_identity_selects_locked_registration_without_creating_an_account(string $email, string $type): void
    {
        $count = User::count();
        $this->fakeCallback($email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('register', ['source' => 'google']));
        $this->get(route('register', ['source' => 'google']))->assertOk()->assertSee($email)
            ->assertSee('readonly', false)->assertSee('name="registration_source" value="google"', false);
        $this->assertSame($type, session('google_registration.registration_type'));
        $this->assertSame($count, User::count());
        $this->assertDatabaseCount('oauth_identities', 0);
        $this->assertGuest();
    }

    #[DataProvider('newAccounts')]
    public function test_completed_google_registration_is_verified_pending_sao_without_grants(string $email, string $type): void
    {
        $this->fakeCallback($email);
        $this->get(route('auth.google.callback'));
        $this->post(route('register.store'), $this->payload($email, $type))->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $record = BorrowerRegistration::firstOrFail();
        $this->assertNotNull($record->user->email_verified_at);
        $this->assertNotNull($record->submitted_for_review_at);
        $this->assertSame('PENDING', $record->status->value);
        $this->assertSame(0, $record->user->roles()->count());
        $this->assertDatabaseMissing('user_organizational_units', ['user_id' => $record->user_id]);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $record->user_id]);
        $this->assertDatabaseHas('oauth_identities', ['user_id' => $record->user_id, 'provider' => 'google', 'subject' => 'new-provider-subject']);
        $this->assertFalse($record->user->mayBorrow());
        $this->assertDatabaseCount('allocations', 0);
        if ($type === 'EXTERNAL') $this->assertNull($record->user->organizational_unit_id);
        $this->assertNull(session('google_registration'));
        $this->fakeCallback($email);
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->actingAs($this->sao())->get(route('registrations.index'))->assertSee($email);
    }

    public function test_google_email_and_type_cannot_be_changed_by_posted_fields(): void
    {
        $this->fakeCallback('applicant@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        $this->post(route('register.store'), $this->payload('fabricated@gmail.com', 'EXTERNAL'))->assertSessionHasErrors(['email', 'registration_type']);
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_missing_or_expired_google_context_cannot_create_a_verified_user(): void
    {
        $this->post(route('register.store'), $this->payload('applicant@cspc.edu.ph', 'EMPLOYEE'))->assertSessionHasErrors('registration_source');
        $this->fakeCallback('applicant@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        $this->travel(11)->minutes();
        $this->post(route('register.store'), $this->payload('applicant@cspc.edu.ph', 'EMPLOYEE'))->assertSessionHasErrors('registration_source');
        $this->assertDatabaseCount('borrower_registrations', 0);
    }

    public function test_unverified_google_identity_and_existing_email_are_not_onboarded_or_auto_linked(): void
    {
        $this->fakeCallback('applicant@gmail.com', false);
        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');
        $this->assertNull(session('google_registration'));
        User::factory()->create(['email' => 'applicant@gmail.com']);
        $this->fakeCallback('applicant@gmail.com');
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'))->assertSessionHasErrors('google');
        $this->assertNull(session('google_registration'));
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_identity_collision_rolls_back_application_and_private_bytes(): void
    {
        $this->fakeCallback('applicant@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        OauthIdentity::create(['user_id' => User::firstOrFail()->id, 'provider' => 'google', 'subject' => 'new-provider-subject']);
        $users = User::count();
        $this->post(route('register.store'), $this->payload('applicant@cspc.edu.ph', 'EMPLOYEE'))->assertSessionHasErrors('email');
        $this->assertSame($users, User::count());
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->assertDatabaseCount('stored_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('registration-ids'));
    }

    public function test_manual_registration_enters_sao_queue_once_only_after_correct_otp(): void
    {
        $data = $this->payload(); $data['registration_source'] = 'manual';
        $this->post(route('register.store'), $data)->assertRedirect(route('account.verification.show', ['email' => $data['email']]));
        $record = BorrowerRegistration::firstOrFail();
        $this->assertNull($record->submitted_for_review_at);
        $this->assertDatabaseMissing('notification_events', ['event_code' => 'BORROWER_REGISTRATION_SUBMITTED']);
        $this->actingAs($this->sao())->get(route('registrations.index'))->assertDontSee($data['email']);
        $this->get(route('registrations.show', $record))->assertForbidden();
        $this->post(route('registrations.decide', $record), ['decision' => 'APPROVED'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        DB::table('email_verification_otps')->where('user_id', $record->user_id)->update(['code_hash' => Hash::make('123456')]);
        $this->post(route('account.verification.verify'), ['email' => $data['email'], 'otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertNull($record->fresh()->submitted_for_review_at);
        $this->post(route('account.verification.verify'), ['email' => $data['email'], 'otp' => '123456'])->assertRedirect(route('login'));
        $this->assertNotNull($record->fresh()->submitted_for_review_at);
        $this->post(route('account.verification.verify'), ['email' => $data['email'], 'otp' => '123456'])->assertRedirect(route('login'));
        $this->assertSame(1, DB::table('notification_events')->where('event_code', 'BORROWER_REGISTRATION_SUBMITTED')->count());
        $this->assertFalse($record->user->fresh()->mayBorrow());
        $this->actingAs($this->sao())->get(route('registrations.index'))->assertSee($data['email']);
    }

    public function test_registration_ui_uses_one_account_type_control_and_visible_google_option(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get(route('login'))->assertSee('Continue with Google')->assertSee('Sign up');
        $page = $this->get(route('register'))->assertOk()->assertDontSee('<select id="registration-type"', false);
        $this->assertSame(1, substr_count($page->getContent(), '>Register as<'));
    }

    public function test_google_form_cannot_switch_source_to_bypass_its_locked_identity(): void
    {
        $this->fakeCallback('applicant@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        $payload = $this->payload('invented@cspc.edu.ph', 'EMPLOYEE');
        $payload['registration_source'] = 'manual';
        $this->post(route('register.store'), $payload)->assertSessionHasErrors('registration_source');
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->get(route('register'))->assertOk(); // An explicit manual Sign up starts a separate unverified flow.
        $this->assertNull(session('google_registration'));
        $this->post(route('register.store'), $payload)->assertRedirect(route('account.verification.show', ['email' => $payload['email']]));
        $this->assertNull(BorrowerRegistration::firstOrFail()->user->email_verified_at);
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_google_audit_failure_rolls_back_verified_identity_application_and_new_private_upload(): void
    {
        $this->fakeCallback('applicant@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        $users = User::count();
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('Synthetic registration audit failure')));
        $this->post(route('register.store'), $this->payload('applicant@cspc.edu.ph', 'EMPLOYEE'))->assertStatus(500);
        $this->assertSame($users, User::count());
        $this->assertDatabaseCount('borrower_registrations', 0);
        $this->assertDatabaseCount('oauth_identities', 0);
        $this->assertDatabaseCount('stored_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('registration-ids'));
    }

    public function test_review_history_remains_visible_when_an_approved_mailbox_is_later_unverified(): void
    {
        $this->fakeCallback('history@cspc.edu.ph');
        $this->get(route('auth.google.callback'));
        $this->post(route('register.store'), $this->payload('history@cspc.edu.ph', 'EMPLOYEE'))->assertRedirect(route('login'));
        $record = BorrowerRegistration::firstOrFail();
        $this->actingAs($this->sao())->post(route('registrations.decide', $record), ['decision' => 'APPROVED'])->assertSessionHasNoErrors();
        $user = $record->user;
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->sao())->get(route('registrations.index'))->assertOk()->assertSee($user->email);
        $this->get(route('registrations.show', $record))->assertOk();
        $this->post(route('registrations.decide', $record), ['decision' => 'REJECTED', 'remarks' => 'Attempted repeat review'])->assertForbidden();
        $this->assertSame('APPROVED', $record->fresh()->status->value);
    }
}
