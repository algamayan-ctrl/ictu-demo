<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\EmailVerificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class IctuInstitutionalAccountVerificationTest extends TestCase
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
        return User::factory()->create([
            'email' => 'ictu.integration@cspc.edu.ph',
            'access_classification' => AccessClassification::IctuMaintainer,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::where('unit_code', 'ICTU')->firstOrFail()->id,
        ]);
    }

    private function formPayload(array $overrides = []): array
    {
        $ccs = OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail();

        return array_merge([
            'division_code' => $ccs->divisionCode(),
            'organizational_unit_id' => $ccs->id,
            'employee_no' => 'ICTU-REG-999',
            'full_name' => 'New Institutional Borrower',
            'designation' => 'Administrative Aide',
            'employment_type' => 'STAFF',
            'employment_status' => 'FULL_TIME',
            'email' => 'new.borrower@cspc.edu.ph',
            'mobile_no' => '09171234567',
            'access_classification' => 'BORROWER_ONLY',
            'account_status' => 'ACTIVE',
            'password' => 'Complex!Pass2026',
            'password_confirmation' => 'Complex!Pass2026',
        ], $overrides);
    }

    public function test_directory_preserves_registration_heading_and_exposes_manage_and_deactivate_icons(): void
    {
        $ictu = $this->ictu();
        $pending = User::factory()->unverified()->create(['email' => 'pending.borrower@cspc.edu.ph']);

        $this->actingAs($ictu)->get(route('administration.users.index'))
            ->assertOk()
            ->assertSee('Institutional user accounts')
            ->assertSee('Register account')
            ->assertSee('User Accounts')
            ->assertSee('aria-label="Manage ', false)
            ->assertSee('aria-label="Deactivate ', false)
            ->assertSee('Email pending')
            ->assertSee(route('administration.users.edit', $pending), false)
            ->assertSee(route('administration.users.deactivate', $pending), false);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('administration.users.destroy'));
    }

    public function test_ictu_can_deactivate_another_active_account_and_preserve_its_record(): void
    {
        $ictu = $this->ictu();
        $target = User::factory()->create(['email' => 'deactivate.borrower@cspc.edu.ph']);
        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($ictu)
            ->patch(route('administration.users.deactivate', $target))
            ->assertRedirect(route('administration.users.index'));

        $this->assertSame(AccountStatus::Inactive, $target->fresh()->account_status);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => $target->email, 'account_status' => 'INACTIVE']);
        $this->assertDatabaseHas('audit_events', [
            'action_code' => 'USER_ACCOUNT_UPDATED',
            'record_type' => User::class,
            'record_id' => $target->id,
        ]);
    }

    public function test_ictu_cannot_deactivate_own_account(): void
    {
        $ictu = $this->ictu();

        $this->actingAs($ictu)
            ->patch(route('administration.users.deactivate', $ictu))
            ->assertRedirect(route('administration.users.index'))
            ->assertSessionHasErrors('account_status');

        $this->assertSame(AccountStatus::Active, $ictu->fresh()->account_status);
    }

    public function test_ictu_can_reactivate_only_another_verified_inactive_account(): void
    {
        $ictu = $this->ictu();
        $inactive = User::factory()->create([
            'email' => 'reactivate.borrower@cspc.edu.ph',
            'account_status' => AccountStatus::Inactive,
        ]);
        $pending = User::factory()->unverified()->create([
            'email' => 'pending-reactivate@cspc.edu.ph',
            'account_status' => AccountStatus::Inactive,
        ]);

        $this->actingAs($ictu)
            ->patch(route('administration.users.reactivate', $inactive))
            ->assertRedirect(route('administration.users.index'));

        $this->assertSame(AccountStatus::Active, $inactive->fresh()->account_status);
        $this->assertDatabaseHas('audit_events', [
            'action_code' => 'USER_ACCOUNT_UPDATED',
            'record_type' => User::class,
            'record_id' => $inactive->id,
        ]);

        $this->patch(route('administration.users.reactivate', $pending))
            ->assertSessionHasErrors('account');
        $this->assertSame(AccountStatus::Inactive, $pending->fresh()->account_status);
    }

    public function test_borrower_cannot_deactivate_another_account(): void
    {
        $borrower = User::factory()->create(['email' => 'another.borrower@cspc.edu.ph']);
        $target = User::factory()->create(['email' => 'active.target@cspc.edu.ph']);

        $this->actingAs($borrower)
            ->patch(route('administration.users.deactivate', $target))
            ->assertForbidden();

        $this->assertSame(AccountStatus::Active, $target->fresh()->account_status);
    }

    public function test_ictu_registration_stays_pending_until_signed_link_is_opened(): void
    {
        $this->actingAs($this->ictu())->post(route('administration.users.store'), $this->formPayload())
            ->assertRedirect(route('administration.users.index'));

        $newUser = User::where('email', 'new.borrower@cspc.edu.ph')->firstOrFail();
        $this->assertNull($newUser->email_verified_at);
        $this->assertFalse($newUser->hasVerifiedInstitutionalEmail());

        $url = URL::temporarySignedRoute('email.verification.verify', now()->addHours(24), [
            'user' => $newUser->id,
            'hash' => app(EmailVerificationService::class)->emailHash($newUser->email),
        ]);
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($newUser->fresh()->hasVerifiedInstitutionalEmail());
    }

    public function test_verification_base_url_automatically_uses_shared_request_host_when_app_url_is_loopback(): void
    {
        config(['app.url' => 'http://127.0.0.1:8080']);

        $request = Request::create('http://spmu-campus.local:8080/administration/users', 'POST');
        app()->instance('request', $request);

        $this->assertSame(
            'http://spmu-campus.local:8080',
            app(EmailVerificationService::class)->verificationBaseUrl()
        );
    }

    public function test_host_independent_verification_link_can_be_opened_through_another_shared_host(): void
    {
        config(['app.url' => 'https://spmu.cspc.edu.ph']);

        $pending = User::factory()->unverified()->create([
            'email' => 'cross.device@cspc.edu.ph',
        ]);

        $url = app(EmailVerificationService::class)->verificationUrl($pending);
        $this->assertStringStartsWith('https://spmu.cspc.edu.ph/email/verify/', $url);

        $parts = parse_url($url);
        $pathAndQuery = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        auth()->logout();
        $this->get('https://another-shared-host.example'.$pathAndQuery)
            ->assertRedirect(route('login'));

        $this->assertTrue($pending->fresh()->hasVerifiedInstitutionalEmail());
    }

    public function test_registration_refuses_unapproved_email_domain(): void
    {
        $this->actingAs($this->ictu())
            ->post(route('administration.users.store'), $this->formPayload(['email' => 'wrong@gmail.com']))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'wrong@gmail.com']);
    }

    public function test_edited_email_is_unverified_and_previous_address_link_cannot_verify(): void
    {
        $this->actingAs($this->ictu());
        $ccs = OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail();
        $borrower = User::factory()->create([
            'email' => 'original@cspc.edu.ph',
            'organizational_unit_id' => $ccs->id,
            'designation' => 'Administrative Aide',
        ]);
        $oldLink = URL::temporarySignedRoute('email.verification.verify', now()->addHour(), [
            'user' => $borrower->id,
            'hash' => app(EmailVerificationService::class)->emailHash($borrower->email),
        ]);

        $payload = $this->formPayload([
            'employee_no' => $borrower->employee_no,
            'email' => 'changed@cspc.edu.ph',
            'full_name' => $borrower->full_name,
            'password' => '',
            'password_confirmation' => '',
        ]);
        $this->put(route('administration.users.update', $borrower), $payload)
            ->assertRedirect(route('administration.users.index'));
        $this->assertNull($borrower->fresh()->email_verified_at);

        auth()->logout();
        $this->get($oldLink)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertNull($borrower->fresh()->email_verified_at);
    }

    public function test_pending_account_is_rejected_for_local_login_and_existing_sessions(): void
    {
        $pending = User::factory()->unverified()->create([
            'email' => 'not.verified@cspc.edu.ph',
            'password' => 'Complex!Pass2026',
        ]);
        $this->post(route('login.store'), [
            'email' => $pending->email,
            'password' => 'Complex!Pass2026',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($pending)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_only_ictu_can_resend_a_pending_link(): void
    {
        $pending = User::factory()->unverified()->create(['email' => 'resend@cspc.edu.ph']);
        $this->actingAs($this->ictu())
            ->post(route('administration.users.email-verification.resend', $pending))
            ->assertRedirect(route('administration.users.edit', $pending));
        $this->assertNull($pending->fresh()->email_verified_at);

        $borrower = User::factory()->create(['email' => 'another@cspc.edu.ph']);
        $this->actingAs($borrower)
            ->post(route('administration.users.email-verification.resend', $pending))
            ->assertForbidden();
    }
}
